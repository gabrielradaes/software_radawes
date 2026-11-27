<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use App\Services\VariantBuilder;
use Tests\TestCase;

/** API de la app del celular (/app): entrar, datos, vender, «Mi día» y permisos. */
class AppCelularTest extends TestCase
{
    private function modelo(int $qty = 2): array
    {
        $p = Product::create(['category_id' => Category::where('slug', 'botines')->value('id'), 'name' => 'Botín Chelsea', 'code' => 'CH-210',
            'slug' => 'botin-chelsea', 'base_price' => 450, 'is_active' => true, 'published_at' => now()]);
        $p->branches()->attach($this->branch('comercio')->id, ['is_active' => true]);
        $v = VariantBuilder::generate($p, [VariantBuilder::value('color', 'Negro')->id], [VariantBuilder::value('talla', '40')->id])->first();
        Stock::where('product_variant_id', $v->id)->where('branch_id', $this->branch('comercio')->id)->first()->move('entrada', $qty);

        return [$p, $v];
    }

    public function test_entrar_y_salir_desde_la_app(): void
    {
        $this->getJson('/api/app/me')->assertOk()->assertJson(['user' => null]);
        $sup = $this->supervisor('comercio');

        $this->postJson('/api/app/login', ['email' => $sup->email, 'password' => 'mala'])->assertStatus(422);
        $sup->update(['password' => 'clave-segura-123']);
        $r = $this->postJson('/api/app/login', ['email' => strtoupper($sup->email), 'password' => 'clave-segura-123'])->assertOk();
        $r->assertJsonPath('user.id', $sup->id)->assertJsonPath('user.scoped', true)
            ->assertJsonPath('can.sell', true)->assertJsonPath('can.entry', true)->assertJsonPath('can.new_model', true);
        $this->assertCount(1, $r->json('branches'));   // el supervisor solo ve su tienda

        $this->postJson('/api/app/logout')->assertOk();
        $this->getJson('/api/app/hoy')->assertStatus(401);

        $sup->update(['is_active' => false]);
        $this->postJson('/api/app/login', ['email' => $sup->email, 'password' => 'clave-segura-123'])->assertStatus(422);
    }

    public function test_vender_desde_la_app_y_verlo_en_mi_dia(): void
    {
        [$p, $v] = $this->modelo(2);
        $vend = $this->vendedor('comercio');

        $this->actingAs($vend)->postJson('/api/app/ventas', ['items' => [['variant_id' => $v->id, 'quantity' => 3]], 'payment_method' => 'qr'])
            ->assertStatus(422);   // no alcanza
        $this->actingAs($vend)->postJson('/api/app/ventas', ['items' => [['variant_id' => $v->id, 'quantity' => 1]], 'payment_method' => 'qr',
            'customer_phone' => '70011222'])->assertStatus(422)->assertJsonValidationErrors('customer_name');

        $r = $this->actingAs($vend)->postJson('/api/app/ventas', ['items' => [['variant_id' => $v->id, 'quantity' => 1, 'price' => 430]],
            'payment_method' => 'qr', 'customer_phone' => '70011222', 'customer_name' => 'Carlos Mamani'])->assertOk();
        $this->assertEquals(430, $r->json('total'));
        $sale = Sale::where('code', $r->json('code'))->firstOrFail();
        $this->assertSame($this->branch('comercio')->id, $sale->branch_id);
        $this->assertSame('Carlos Mamani', $sale->customer->name);
        $this->assertSame(1, (int) Stock::where('product_variant_id', $v->id)->where('branch_id', $this->branch('comercio')->id)->value('quantity'));

        $hoy = $this->actingAs($vend)->getJson('/api/app/hoy')->assertOk();
        $this->assertSame(1, $hoy->json('sales.count'));
        $this->assertStringContainsString('Botín Chelsea', $hoy->json('sales.list.0.items.0'));
        $this->assertContains('Vendió desde la app', collect($hoy->json('activity'))->pluck('text')->all());

        // Las búsquedas de stock del panel responden JSON para la app
        $this->actingAs($vend)->getJson('/admin/stock/buscar?q=CH-210')->assertOk()->assertJsonPath('0.code', 'CH-210');
        $this->actingAs($vend)->getJson('/admin/stock/modelo/'.$p->id)->assertOk()->assertJsonPath('product.code', 'CH-210');
    }

    public function test_permisos_en_la_app(): void
    {
        $vend = $this->vendedor('comercio');
        $me = $this->actingAs($vend)->getJson('/api/app/me')->assertOk();
        $me->assertJsonPath('can.sell', true)->assertJsonPath('can.entry', false)->assertJsonPath('can.new_model', false);

        // El vendedor no ingresa stock ni crea modelos
        $this->actingAs($vend)->postJson('/admin/stock/ingresar', ['name' => 'X', 'category_id' => Category::value('id'), 'price' => 1])->assertForbidden();

        // El supervisor de Comercio no vende en otra tienda aunque lo pida
        [, $v] = $this->modelo(1);
        $sup = $this->supervisor('comercio');
        $this->actingAs($sup)->postJson('/api/app/ventas', ['branch_id' => $this->branch('calacoto')->id, 'items' => [['variant_id' => $v->id, 'quantity' => 1]], 'payment_method' => 'efectivo'])
            ->assertOk();
        $this->assertSame($this->branch('comercio')->id, Sale::latest('id')->value('branch_id'));

        // Historial del asistente para la app
        $this->actingAs($sup)->postJson('/admin/asistente/mensaje', ['text' => 'hola'])->assertOk();
        $this->actingAs($sup)->getJson('/api/app/asistente')->assertOk()->assertJsonPath('messages.1.role', 'assistant');
    }
}
