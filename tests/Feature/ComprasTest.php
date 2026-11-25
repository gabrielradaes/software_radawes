<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SizeCurve;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\DatabaseBackup;
use App\Services\SaleService;
use Tests\TestCase;

/** Copias de seguridad, auditoría, costos, proveedores, compras con curva de tallas y utilidad. */
class ComprasTest extends TestCase
{
    private function producto(string $branch = 'calacoto'): Product
    {
        $p = Product::create([
            'category_id' => Category::where('slug', 'botines')->value('id'), 'name' => 'Botín Chelsea', 'code' => 'CH-210',
            'slug' => 'botin-chelsea', 'base_price' => 450, 'is_active' => true, 'published_at' => now(),
        ]);
        $p->branches()->attach($this->branch($branch)->id, ['is_active' => true]);

        return $p;
    }

    public function test_copia_de_seguridad_se_crea_descarga_y_es_solo_para_admin(): void
    {
        $antes = DatabaseBackup::list();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/respaldos')->assertOk();
        $this->actingAs($admin)->post('/admin/respaldos')->assertRedirect();
        $nuevas = array_values(array_diff(array_column(DatabaseBackup::list(), 'name'), array_column($antes, 'name')));
        $this->assertCount(1, $nuevas);
        $path = DatabaseBackup::dir().'/'.$nuevas[0];
        $sql = str_ends_with($path, '.gz') ? gzdecode(file_get_contents($path)) : file_get_contents($path);
        $this->assertStringContainsString('CREATE TABLE `products`', $sql);
        $this->assertStringContainsString($admin->email, $sql);

        $this->actingAs($admin)->get('/admin/respaldos/'.$nuevas[0])->assertOk()->assertDownload($nuevas[0]);
        $this->actingAs($admin)->get('/admin/respaldos/..%2F.env')->assertNotFound();
        $this->actingAs($this->supervisor())->get('/admin/respaldos')->assertForbidden();
        $this->actingAs($this->supervisor())->get('/admin/respaldos/'.$nuevas[0])->assertForbidden();
        @unlink($path);
    }

    public function test_auditoria_registra_precios_usuarios_y_anulaciones(): void
    {
        $admin = $this->admin();
        $p = $this->producto();
        $this->actingAs($admin)->put("/admin/productos/{$p->id}", [
            'name' => $p->name, 'category_id' => $p->category_id, 'base_price' => 480, 'cost' => 260, 'published' => 1, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'actualizado')->where('subject_type', Product::class)->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['450.00', '480.00'], $log->changes['Precio']);
        $this->assertSame([null, '260.00'], $log->changes['Costo']);

        $this->actingAs($admin)->get('/admin/actividad')->assertOk()->assertSee('Producto modificado: Botín Chelsea');
        // El supervisor ve el historial de su tienda, pero no lo que hizo el administrador
        $this->actingAs($this->supervisor())->get('/admin/actividad')->assertOk()->assertDontSee('Producto modificado: Botín Chelsea');
    }

    public function test_ingreso_con_curva_crea_variantes_stock_kardex_y_costo(): void
    {
        $sup = $this->supervisor('calacoto');
        $p = $this->producto();
        $curva = SizeCurve::where('name', 'Caballero 38-43 (normal)')->firstOrFail();
        $this->assertSame(12, $curva->pairs());

        $this->actingAs($sup)->get('/admin/compras/nueva')->assertOk()->assertSee('CH-210');
        $sizes = $curva->items->pluck('quantity', 'size')->map(fn ($q) => $q * 2)->all();   // curva ×2
        $this->actingAs($sup)->post('/admin/compras', [
            'branch_id' => $this->branch('calacoto')->id, 'new_supplier' => 'Calçados Brasil Ltda', 'invoice_number' => 'F-1001',
            'purchased_at' => now()->toDateString(),
            'blocks' => [
                ['product_id' => $p->id, 'color' => 'negro', 'sizes' => $sizes, 'unit_cost' => 250],
                ['product_id' => $p->id, 'color' => 'Café', 'sizes' => ['40' => 2, '41' => 1, '44' => 0], 'unit_cost' => 250],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $purchase = Purchase::firstOrFail();
        $this->assertSame([27, '6750.00'], [$purchase->pairs, $purchase->total]);
        $this->assertSame('Calçados Brasil Ltda', $purchase->supplier->name);
        $this->assertSame(8, $p->variants()->count());   // 6 tallas negro + 2 café
        $this->assertSame('250.00', $p->fresh()->cost);
        $negro40 = $p->variants()->with('attributeValues.attribute')->get()->first(fn ($v) => $v->valueOf('talla') === '40' && $v->valueOf('color') === 'Negro');
        $this->assertSame(6, $negro40->stockInBranch($this->branch('calacoto')->id));
        $this->assertSame(8, StockMovement::where('type', 'compra')->where('reference_type', Purchase::class)->count());

        $this->actingAs($sup)->get("/admin/compras/{$purchase->id}")->assertOk()->assertSee('F-1001')->assertSee('margen 44%');
        $this->actingAs($sup)->get('/admin/compras')->assertOk()->assertSee($purchase->code);

        // La venta guarda el costo → ganancia
        SaleService::register($sup, $this->branch('calacoto'), null, [['variant' => $negro40, 'quantity' => 2]]);
        $this->assertSame('250.00', Sale::first()->items->first()->unit_cost);
        $this->actingAs($sup)->get('/admin/ventas')->assertSee('Ganancia (mes)')->assertSee('Bs 400,00');
        $this->actingAs($sup)->get('/admin/stock')->assertSee('Inventario a costo')->assertSee('Bs 6.250,00');

        // Sin pares → error claro; otra tienda → prohibido; vendedor → sin acceso
        $this->actingAs($sup)->post('/admin/compras', ['branch_id' => $this->branch('calacoto')->id, 'purchased_at' => now()->toDateString(),
            'blocks' => [['product_id' => $p->id, 'color' => 'Negro', 'sizes' => ['40' => 0], 'unit_cost' => 250]]])->assertSessionHasErrors('blocks');
        $this->actingAs($sup)->post('/admin/compras', ['branch_id' => $this->branch('el-alto')->id, 'purchased_at' => now()->toDateString(),
            'blocks' => [['product_id' => $p->id, 'color' => 'Negro', 'sizes' => ['40' => 1], 'unit_cost' => 250]]])->assertForbidden();
        $vend = $this->vendedor('calacoto');
        $this->actingAs($vend)->get('/admin/compras')->assertForbidden();
        $this->actingAs($vend)->get('/admin/stock')->assertOk()->assertDontSee('Inventario a costo');
        $this->actingAs($vend)->get('/admin/ventas')->assertOk()->assertDontSee('Ganancia (mes)');
    }

    public function test_proveedores_y_curvas(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/proveedores', ['name' => 'Bottero', 'phone' => '+55 51 9999', 'country' => 'Brasil'])->assertRedirect();
        $s = Supplier::where('name', 'Bottero')->firstOrFail();
        $this->actingAs($admin)->put("/admin/proveedores/{$s->id}", ['name' => 'Bottero Calçados', 'is_active' => 1])->assertRedirect();
        $this->assertSame('Bottero Calçados', $s->fresh()->name);
        $this->actingAs($admin)->get('/admin/proveedores')->assertOk()->assertSee('Bottero Calçados');

        $this->actingAs($admin)->post('/admin/curvas', ['name' => 'Niño 27-32', 'sizes' => ['27' => 1, '28' => 2, '29' => 0]])->assertRedirect();
        $c = SizeCurve::where('name', 'Niño 27-32')->firstOrFail();
        $this->assertSame(['27' => 1, '28' => 2], $c->items->pluck('quantity', 'size')->all());
        $this->actingAs($admin)->post('/admin/curvas', ['name' => 'Vacía', 'sizes' => ['40' => 0]])->assertSessionHasErrors('sizes');
        $this->actingAs($admin)->delete("/admin/curvas/{$c->id}")->assertRedirect();
        $this->assertNull(SizeCurve::find($c->id));
    }
}
