<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Stock;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Support\Barcode;
use Tests\TestCase;

/** Códigos de barras, etiquetas, venta por escaneo y caja diaria. */
class CajaEtiquetasTest extends TestCase
{
    private function compra(int $pares40 = 3): Purchase
    {
        $p = Product::create([
            'category_id' => Category::where('slug', 'botines')->value('id'), 'name' => 'Botín Chelsea', 'code' => 'CH-210',
            'slug' => 'botin-chelsea', 'base_price' => 450, 'is_active' => true, 'published_at' => now(),
        ]);

        return PurchaseService::register($this->admin(), $this->branch('calacoto'), null,
            [['product' => $p, 'color' => 'Negro', 'sizes' => ['40' => $pares40, '41' => 2], 'unit_cost' => 250]]);
    }

    public function test_codigo_de_barras_code128_valido(): void
    {
        $bits = Barcode::modules('P001-NEG-40');
        // inicio B (11010010000) + 11 caracteres + verificador + fin (1100011101011) = 11·13 + 13 módulos
        $this->assertSame(11 * 13 + 13, strlen($bits));
        $this->assertStringStartsWith('11010010000', $bits);
        $this->assertStringEndsWith('1100011101011', $bits);
        $this->assertStringContainsString('<svg', Barcode::svg('P001-NEG-40'));
    }

    public function test_etiquetas_por_ingreso_y_por_producto(): void
    {
        $purchase = $this->compra();
        $sup = $this->supervisor('calacoto');
        $sku = $purchase->items->first()->variant->sku;

        $html = $this->actingAs($sup)->get("/admin/etiquetas?ingreso={$purchase->id}")->assertOk()->assertSee($sku)->assertSee('Bs 450,00')->getContent();
        $this->assertSame(5 * 2, substr_count($html, 'class="label"'), '5 pares → 5 etiquetas (en cada tamaño de hoja)');

        $product = $purchase->items->first()->variant->product;
        $html = $this->actingAs($sup)->get("/admin/etiquetas?producto={$product->id}&cantidad=una")->assertOk()->getContent();
        $this->assertSame(2 * 2, substr_count($html, 'class="label"'));
        $this->actingAs($sup)->get("/admin/productos/{$product->id}/variantes")->assertSee('Etiquetas');

        $this->actingAs($this->supervisor('el-alto'))->get("/admin/etiquetas?ingreso={$purchase->id}")->assertForbidden();
    }

    public function test_buscador_de_modelos_grilla_y_escaner(): void
    {
        $purchase = $this->compra();
        $vend = $this->vendedor('calacoto');
        $variant = $purchase->items->first()->variant;

        $this->actingAs($vend)->get('/admin/ventas/nueva')->assertOk()->assertSee('Busca por código o nombre del modelo');
        $this->actingAs($vend)->get('/admin/stock')->assertOk()->assertSee('Consulta rápida');

        // Buscar por código, por nombre y por SKU
        $this->actingAs($vend)->getJson('/admin/stock/buscar?q=CH-2')->assertOk()->assertJsonPath('0.code', 'CH-210')->assertJsonPath('0.available', 5);
        $this->actingAs($vend)->getJson('/admin/stock/buscar?q=chelsea')->assertJsonCount(1);
        $this->actingAs($vend)->getJson('/admin/stock/buscar?q='.$variant->sku)->assertJsonPath('0.code', 'CH-210');
        $this->actingAs($vend)->getJson('/admin/stock/buscar?q=zz')->assertJsonCount(0);

        // Grilla colores × tallas por tienda (la mía primero)
        $m = $this->actingAs($vend)->getJson("/admin/stock/modelo/{$variant->product_id}")->assertOk()->json();
        $cal = $this->branch('calacoto')->id;
        $this->assertSame($cal, $m['branch_id']);
        $this->assertSame($cal, $m['branches'][0]['id']);
        $this->assertSame(['40', '41'], $m['sizes']);
        $this->assertSame(3, $m['cells'][$cal]['Negro']['40']['available']);
        $this->assertSame(0, $m['cells'][$this->branch('el-alto')->id]['Negro']['40']['available']);

        // Código de barras
        $this->actingAs($vend)->getJson('/admin/stock/sku?code='.strtolower($variant->sku))->assertOk()->assertJson(['ok' => true, 'available' => 3]);
        $this->actingAs($vend)->getJson('/admin/stock/sku?code=NO-EXISTE')->assertNotFound();

        // Cliente por celular
        \App\Models\Customer::create(['name' => 'Carlos', 'phone' => '70011222', 'is_active' => true]);
        $this->actingAs($vend)->getJson('/admin/clientes/buscar?phone=700 11 222')->assertJson(['found' => true, 'name' => 'Carlos']);
        $this->actingAs($vend)->getJson('/admin/clientes/buscar?phone=79999999')->assertJson(['found' => false]);
    }

    public function test_caja_abrir_vender_y_cerrar_con_arqueo(): void
    {
        $purchase = $this->compra();
        $variant = $purchase->items->first()->variant;
        $vend = $this->vendedor('calacoto');
        $cal = $this->branch('calacoto')->id;

        $this->actingAs($vend)->get('/admin/caja')->assertOk()->assertSee('Abrir caja');
        $this->actingAs($vend)->get('/admin/ventas/nueva')->assertSee('La caja de esta tienda está cerrada');
        $this->actingAs($vend)->post('/admin/caja/abrir', ['branch_id' => $cal, 'opening_amount' => 100])->assertRedirect();
        $this->actingAs($vend)->post('/admin/caja/abrir', ['branch_id' => $cal, 'opening_amount' => 50])->assertSessionHasErrors('caja');
        $session = CashSession::openFor($cal);
        $this->assertNotNull($session);

        SaleService::register($vend, $this->branch('calacoto'), null, [['variant' => $variant, 'quantity' => 1]], 'efectivo');
        SaleService::register($vend, $this->branch('calacoto'), null, [['variant' => $variant, 'quantity' => 1]], 'qr');
        $anulada = SaleService::register($vend, $this->branch('calacoto'), null, [['variant' => $variant, 'quantity' => 1]], 'efectivo');
        SaleService::cancel($anulada, $this->supervisor('calacoto'));
        $this->assertSame(3, Sale::where('cash_session_id', $session->id)->count());
        $this->assertSame(550.0, $session->expectedCash());   // 100 inicial + 450 en efectivo (la anulada no cuenta)

        $this->actingAs($vend)->get('/admin/caja')->assertSee('Bs 550,00');
        $this->actingAs($vend)->post("/admin/caja/{$session->id}/cerrar", ['counted_cash' => 540, 'notes' => 'Pasaje'])->assertRedirect()
            ->assertSessionHas('status', 'Caja cerrada con una diferencia de Bs -10,00.');
        $session->refresh();
        $this->assertSame(['550.00', '540.00', '-10.00'], [$session->expected_cash, $session->counted_cash, $session->difference]);
        $this->assertEquals(450, $session->totals['qr']['total']);
        $this->assertNull(CashSession::openFor($cal));
        $this->actingAs($vend)->get('/admin/caja')->assertSee('Diferencia Bs -10,00');

        // Otra tienda no puede abrir/cerrar mi caja
        $this->actingAs($this->vendedor('el-alto'))->post('/admin/caja/abrir', ['branch_id' => $cal, 'opening_amount' => 0])->assertForbidden();
    }
}
