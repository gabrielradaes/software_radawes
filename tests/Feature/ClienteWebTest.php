<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerTask;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Stock;
use App\Services\PurchaseService;
use Tests\TestCase;

/** Lo que ve y hace el cliente en la página: disponibilidad sin cantidades exactas, otras tiendas y separar un par. */
class ClienteWebTest extends TestCase
{
    private Product $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->p = Product::create([
            'category_id' => Category::where('slug', 'botines')->value('id'), 'name' => 'Botín Chelsea', 'code' => 'CH-210',
            'slug' => 'botin-chelsea', 'base_price' => 450, 'is_active' => true, 'published_at' => now(),
        ]);
        PurchaseService::register($this->admin(), $this->branch('calacoto'), null, [['product' => $this->p, 'color' => 'Negro', 'sizes' => ['40' => 5, '41' => 1], 'unit_cost' => 250]]);
        PurchaseService::register($this->admin(), $this->branch('el-alto'), null, [['product' => $this->p, 'color' => 'Negro', 'sizes' => ['42' => 2], 'unit_cost' => 250]]);
    }

    private function variant(string $talla)
    {
        return $this->p->variants()->with('attributeValues.attribute')->get()->first(fn ($v) => $v->valueOf('talla') === $talla);
    }

    private function matrix(string $html): array
    {
        preg_match('/matrix: JSON\.parse\(\'(.+?)\'\)/', $html, $m);

        return json_decode(json_decode('"'.($m[1] ?? '').'"'), true) ?? [];
    }

    public function test_la_pagina_no_muestra_cantidades_exactas_y_avisa_otras_tiendas(): void
    {
        $html = $this->get('/calacoto/producto/botin-chelsea')->assertOk()
            ->assertSee('Hay tallas disponibles')->assertDontSee('pares en stock')->assertSee('Separar')->getContent();
        $m = $this->matrix($html);
        $this->assertSame(2, $m['Negro']['40']['stock'], 'Muchos pares → "disponible" (sin número)');
        $this->assertSame(1, $m['Negro']['41']['stock'], '1 par → "últimos pares"');
        $this->assertSame(0, $m['Negro']['42']['stock']);
        $this->assertStringContainsString('/el-alto/producto/botin-chelsea', $m['Negro']['42']['elsewhere'][0]['url'], 'La 42 hay en El Alto');
        $this->assertArrayNotHasKey('sku', $m['Negro']['40']);
    }

    public function test_la_tienda_puede_guardarse_pares_para_el_mostrador(): void
    {
        $this->branch('calacoto')->update(['web_stock_buffer' => 1]);
        $m = $this->matrix($this->get('/calacoto/producto/botin-chelsea')->getContent());
        $this->assertSame(0, $m['Negro']['41']['stock'], 'Queda 1 y la tienda se guarda 1: en la web figura agotada');
        $this->assertSame(2, $m['Negro']['40']['stock']);

        $this->post('/calacoto/producto/botin-chelsea/separar', ['variant_id' => $this->variant('41')->id, 'sep_name' => 'Ana', 'sep_phone' => '70011222'])
            ->assertSessionHasErrors('separar');
        $this->assertSame(0, Reservation::count());
    }

    public function test_el_cliente_separa_un_par_desde_la_pagina(): void
    {
        $v = $this->variant('40');
        $this->post('/calacoto/producto/botin-chelsea/separar', ['variant_id' => $v->id, 'sep_name' => 'Ana Flores', 'sep_phone' => '7001 1222'])
            ->assertRedirect()->assertSessionHas('web_order');

        $res = Reservation::firstOrFail();
        $ana = Customer::where('phone', '70011222')->firstOrFail();
        $this->assertSame(['web', 'activo', $ana->id, '0.00', '450.00'], [$res->source, $res->status, $res->customer_id, $res->deposit, $res->total]);
        $this->assertSame(today()->addDays(2)->toDateString(), $res->expires_at->toDateString());
        $this->assertSame(1, (int) Stock::where('product_variant_id', $v->id)->where('branch_id', $this->branch('calacoto')->id)->value('reserved'));
        $this->assertTrue(CustomerTask::where('title', 'like', "%pedido web {$res->code}%")->exists(), 'La tienda recibe la tarea de confirmar');

        // Lo ve en el panel y en su cuenta
        $this->actingAs($this->supervisor('calacoto'))->get('/admin/apartados')->assertSee($res->code)->assertSee('desde la página');
        $ana->update(['password' => 'secreto123', 'email_verified_at' => now()]);
        $this->actingAs($ana, 'customer')->get('/calacoto/mi-cuenta')->assertOk()->assertSee('Mis pedidos')->assertSee($res->code);

        // Límite de pares separados a la vez
        foreach ([1, 2] as $_) {
            $this->post('/calacoto/producto/botin-chelsea/separar', ['variant_id' => $v->id, 'sep_name' => 'Ana', 'sep_phone' => '70011222']);
        }
        $this->post('/calacoto/producto/botin-chelsea/separar', ['variant_id' => $v->id, 'sep_name' => 'Ana', 'sep_phone' => '70011222'])->assertSessionHasErrors('separar');
        $this->assertSame(3, Reservation::count());

        // Validaciones
        $this->post('/calacoto/producto/botin-chelsea/separar', ['variant_id' => $v->id, 'sep_name' => 'X', 'sep_phone' => '123'])->assertSessionHasErrors('sep_phone');
        $this->post('/calacoto/producto/botin-chelsea/separar', ['variant_id' => 999999, 'sep_name' => 'X', 'sep_phone' => '70000000'])->assertNotFound();
    }
}
