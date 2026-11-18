<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerTask;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockCount;
use App\Models\Transfer;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Tests\TestCase;

/** Cambios de talla, apartados, inventario físico, traspasos en 2 pasos y caja con movimientos. */
class OperacionTest extends TestCase
{
    private Product $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->p = Product::create([
            'category_id' => Category::where('slug', 'botines')->value('id'), 'name' => 'Botín Chelsea', 'code' => 'CH-210',
            'slug' => 'botin-chelsea', 'base_price' => 450, 'is_active' => true, 'published_at' => now(),
        ]);
        PurchaseService::register($this->admin(), $this->branch('calacoto'), null,
            [['product' => $this->p, 'color' => 'Negro', 'sizes' => ['40' => 3, '41' => 2, '42' => 1], 'unit_cost' => 250]]);
        $this->p->variants()->whereHas('attributeValues', fn ($q) => $q->where('value', '42'))->first()->update(['price' => 500]);
    }

    private function v(string $talla): ProductVariant
    {
        return $this->p->variants()->with('attributeValues.attribute', 'product')->get()->first(fn ($v) => $v->valueOf('talla') === $talla);
    }

    private function qty(string $talla, string $branch = 'calacoto'): int
    {
        return (int) Stock::where('product_variant_id', $this->v($talla)->id)->where('branch_id', $this->branch($branch)->id)->value('quantity');
    }

    private function abrirCaja(float $inicial = 0): CashSession
    {
        return CashSession::create(['branch_id' => $this->branch('calacoto')->id, 'opened_by' => $this->admin()->id, 'opened_at' => now(), 'opening_amount' => $inicial]);
    }

    public function test_cambio_de_talla_con_diferencia_y_devolucion(): void
    {
        $caja = $this->abrirCaja(100);
        $vend = $this->vendedor('calacoto');
        $c = Customer::create(['name' => 'Ana', 'phone' => '70000001', 'is_active' => true]);
        $sale = SaleService::register($vend, $this->branch('calacoto'), $c, [['variant' => $this->v('40'), 'quantity' => 2]]);   // 900 en efectivo
        $item = $sale->items->first();

        $this->actingAs($vend)->get("/admin/ventas/{$sale->id}")->assertOk()->assertSee('Cambio de talla o devolución');

        // Cambia 1 talla 40 (450) por 1 talla 42 (500): paga 50 por QR
        $this->actingAs($vend)->post("/admin/ventas/{$sale->id}/cambio", [
            'returns' => [$item->id => 1], 'items' => [['variant_id' => $this->v('42')->id, 'quantity' => 1]], 'payment_method' => 'qr', 'reason' => 'Le quedó chico',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Cambio registrado: el cliente pagó Bs 50,00 de diferencia.');
        $this->assertSame([2, 0], [$this->qty('40'), $this->qty('42')]);   // 3−2+1 y 1−1

        // Devuelve el otro talla 40 sin llevarse nada: se le devuelven 450 en efectivo
        $this->actingAs($vend)->post("/admin/ventas/{$sale->id}/cambio", ['returns' => [$item->id => 1], 'items' => [['variant_id' => '', 'quantity' => 1]], 'payment_method' => 'efectivo'])
            ->assertSessionHas('status', 'Devolución registrada: se le devolvió Bs 450,00 al cliente.');
        $this->assertSame(3, $this->qty('40'));

        // No puede devolver más de lo vendido
        $this->actingAs($vend)->post("/admin/ventas/{$sale->id}/cambio", ['returns' => [$item->id => 1], 'payment_method' => 'efectivo'])->assertSessionHasErrors('exchange');

        $this->assertSame(100 + 900 - 450.0, $caja->fresh()->expectedCash());
        $this->assertEquals(50, $caja->totalsByPayment()['qr']['total']);
        $this->assertSame(2, $c->interactions()->count());
    }

    public function test_apartado_reserva_el_stock_retira_y_cobra_el_saldo(): void
    {
        $caja = $this->abrirCaja(0);
        $vend = $this->vendedor('calacoto');
        $this->actingAs($vend)->get('/admin/apartados/nuevo')->assertOk()->assertSee('CH-210');

        $this->actingAs($vend)->post('/admin/apartados', [
            'branch_id' => $this->branch('calacoto')->id, 'customer_phone' => '7000 0002', 'customer_name' => 'Luis Rojas',
            'items' => [['variant_id' => $this->v('41')->id, 'quantity' => 2]], 'deposit' => 300, 'deposit_method' => 'efectivo',
            'expires_at' => today()->addDays(5)->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $res = Reservation::firstOrFail();
        $this->assertSame(['900.00', '300.00', 600.0], [$res->total, $res->deposit, $res->balance()]);
        $this->assertSame(2, (int) Stock::where('product_variant_id', $this->v('41')->id)->where('branch_id', $this->branch('calacoto')->id)->value('reserved'));

        // Apartados: ni se venden ni se muestran como disponibles
        $this->expectExceptionMessageMatches('/disponibles: 0/');
        try {
            SaleService::register($vend, $this->branch('calacoto'), null, [['variant' => $this->v('41'), 'quantity' => 1]]);
        } finally {
            $this->assertTrue(CustomerTask::where('title', 'like', "%{$res->code}%")->exists());

            // Retira: venta por el total, en caja entra solo el saldo
            $this->actingAs($vend)->post("/admin/apartados/{$res->id}/retirar", ['payment_method' => 'qr'])->assertRedirect();
            $res->refresh();
            $this->assertSame('retirado', $res->status);
            $this->assertSame('900.00', $res->sale->total);
            $this->assertSame([0, 0], [$this->qty('41'), (int) Stock::where('product_variant_id', $this->v('41')->id)->where('branch_id', $this->branch('calacoto')->id)->value('reserved')]);
            $tot = $caja->totalsByPayment();
            $this->assertEquals([300, 600], [$tot['efectivo']['total'], $tot['qr']['total']]);
            $this->assertFalse(CustomerTask::where('title', 'like', "%{$res->code}%")->whereNull('done_at')->exists());
        }
    }

    public function test_cancelar_apartado_libera_y_devuelve_adelanto(): void
    {
        $caja = $this->abrirCaja(0);
        $c = Customer::create(['name' => 'Rosa', 'phone' => '70000003', 'is_active' => true]);
        $res = \App\Services\OpsService::reserve($this->admin(), $this->branch('calacoto'), $c, [['variant' => $this->v('40'), 'quantity' => 1]], 100, 'efectivo', today()->addDay());
        $this->actingAs($this->supervisor('calacoto'))->post("/admin/apartados/{$res->id}/cancelar", ['refund' => 1])->assertRedirect();
        $this->assertSame('cancelado', $res->fresh()->status);
        $this->assertSame(0, (int) Stock::where('product_variant_id', $this->v('40')->id)->where('branch_id', $this->branch('calacoto')->id)->value('reserved'));
        $this->assertSame(0.0, $caja->expectedCash());
        $this->actingAs($this->vendedor('el-alto'))->get("/admin/apartados/{$res->id}")->assertForbidden();
    }

    public function test_inventario_fisico_parcial_y_total(): void
    {
        $sup = $this->supervisor('calacoto');
        $this->actingAs($sup)->post('/admin/inventario', ['branch_id' => $this->branch('calacoto')->id, 'scope' => 'parcial'])->assertRedirect();
        $count = StockCount::firstOrFail();
        $this->actingAs($sup)->post('/admin/inventario', ['branch_id' => $this->branch('calacoto')->id, 'scope' => 'total'])->assertSessionHasErrors('count');

        // Un vendedor escanea: la 40 aparece 2 veces (hay 3 en sistema) y la 41 tres veces (hay 2)
        $vend = $this->vendedor('calacoto');
        foreach ([$this->v('40')->sku, strtolower($this->v('40')->sku), $this->v('41')->sku, $this->v('41')->sku, $this->v('41')->sku] as $code) {
            $this->actingAs($vend)->postJson("/admin/inventario/{$count->id}/escanear", ['code' => $code])->assertOk()->assertJson(['ok' => true]);
        }
        $this->actingAs($vend)->postJson("/admin/inventario/{$count->id}/escanear", ['code' => 'NO-EXISTE'])->assertNotFound();
        $this->actingAs($vend)->get("/admin/inventario/{$count->id}")->assertOk()->assertSee('Faltan');
        $this->actingAs($vend)->post("/admin/inventario/{$count->id}/cerrar")->assertForbidden();

        $this->actingAs($sup)->post("/admin/inventario/{$count->id}/cerrar")->assertRedirect();
        $this->assertSame([2, 3, 1], [$this->qty('40'), $this->qty('41'), $this->qty('42')]);   // la 42 no se contó: parcial no la toca
        $this->assertSame(-1, $count->items()->where('product_variant_id', $this->v('40')->id)->value('difference'));

        // Total: lo no contado queda en 0
        $this->actingAs($sup)->post('/admin/inventario', ['branch_id' => $this->branch('calacoto')->id, 'scope' => 'total']);
        $total = StockCount::where('scope', 'total')->firstOrFail();
        $this->actingAs($sup)->postJson("/admin/inventario/{$total->id}/escanear", ['code' => $this->v('40')->sku])->assertOk();
        $this->actingAs($sup)->post("/admin/inventario/{$total->id}/cerrar");
        $this->assertSame([1, 0, 0], [$this->qty('40'), $this->qty('41'), $this->qty('42')]);
    }

    public function test_traspaso_en_dos_pasos(): void
    {
        $sup = $this->supervisor('calacoto');
        $this->actingAs($sup)->get('/admin/traspasos/nuevo')->assertOk();
        $this->actingAs($sup)->post('/admin/traspasos', [
            'from_branch_id' => $this->branch('calacoto')->id, 'to_branch_id' => $this->branch('el-alto')->id,
            'items' => [['variant_id' => $this->v('40')->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $tr = Transfer::firstOrFail();
        $this->assertSame([1, 0], [$this->qty('40'), $this->qty('40', 'el-alto')]);

        // Solo la tienda de destino confirma; el vendedor de origen no puede enviar
        $this->actingAs($this->vendedor('calacoto'))->post("/admin/traspasos/{$tr->id}/recibir")->assertForbidden();
        $this->actingAs($this->vendedor('calacoto'))->get('/admin/traspasos/nuevo')->assertForbidden();
        $this->actingAs($this->vendedor('el-alto'))->get('/admin/traspasos')->assertOk()->assertSee($tr->code);
        $this->actingAs($this->vendedor('el-alto'))->post("/admin/traspasos/{$tr->id}/recibir")->assertRedirect();
        $this->assertSame([1, 2], [$this->qty('40'), $this->qty('40', 'el-alto')]);
        $this->assertTrue($this->p->branches()->where('slug', 'el-alto')->exists());

        // Cancelar devuelve al origen
        $tr2 = \App\Services\OpsService::sendTransfer($sup, $this->branch('calacoto'), $this->branch('comercio'), [['variant' => $this->v('41'), 'quantity' => 1]]);
        $this->actingAs($sup)->post("/admin/traspasos/{$tr2->id}/cancelar")->assertRedirect();
        $this->assertSame([2, 'cancelado'], [$this->qty('41'), $tr2->fresh()->status]);
    }

    public function test_caja_registra_gastos_e_ingresos(): void
    {
        $caja = $this->abrirCaja(200);
        $vend = $this->vendedor('calacoto');
        $this->actingAs($vend)->post("/admin/caja/{$caja->id}/movimiento", ['type' => 'egreso', 'amount' => 15, 'payment_method' => 'efectivo', 'note' => 'Pasaje'])->assertRedirect();
        $this->actingAs($vend)->post("/admin/caja/{$caja->id}/movimiento", ['type' => 'ingreso', 'amount' => 50, 'payment_method' => 'efectivo', 'note' => 'Cambio'])->assertRedirect();
        $this->assertSame(235.0, $caja->expectedCash());
        $this->actingAs($vend)->get('/admin/caja')->assertSee('Pasaje')->assertSee('Gasto / retiro');
    }
}
