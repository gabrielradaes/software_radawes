<?php

namespace Tests\Feature;

use App\Models\AssistantMessage;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Services\VariantBuilder;
use Tests\TestCase;

/**
 * Todo se puede hacer a mano (ingreso de calzado sin el asistente) y el asistente no se confunde
 * con respuestas que no corresponden a lo que preguntó.
 */
class ManualYAsistenteTest extends TestCase
{
    private function decir($user, string $text): array
    {
        return $this->actingAs($user)->postJson('/admin/asistente/mensaje', ['text' => $text])->assertOk()->json();
    }

    private function modelo(string $branch = 'comercio'): Product
    {
        $p = Product::create([
            'category_id' => Category::where('slug', 'mocasines')->value('id') ?? Category::value('id'), 'name' => 'Mocasín Penny', 'code' => 'MP-100',
            'slug' => 'mocasin-penny', 'base_price' => 420, 'is_active' => true, 'published_at' => now(),
        ]);
        $p->branches()->attach($this->branch($branch)->id, ['is_active' => true]);
        VariantBuilder::generate($p, [VariantBuilder::value('color', 'Negro')->id, VariantBuilder::value('color', 'Café')->id], [VariantBuilder::value('talla', '40')->id]);

        return $p->fresh();
    }

    private function stock(Product $p, ?string $talla, ?string $color, string $branch = 'comercio'): int
    {
        $v = $p->variants()->with('attributeValues.attribute')->get()
            ->first(fn ($v) => $v->valueOf('talla') === $talla && $v->valueOf('color') === $color);

        return $v ? (int) Stock::where('product_variant_id', $v->id)->where('branch_id', $this->branch($branch)->id)->value('quantity') : -1;
    }

    // ------------------------------------------------------------------ ingreso manual

    public function test_supervisor_ingresa_pares_de_un_modelo_que_ya_existe_a_mano(): void
    {
        $sup = $this->supervisor('comercio');
        $p = $this->modelo();

        $this->actingAs($sup)->get('/admin/stock/ingresar')->assertOk()->assertSee('¿Qué modelo llegó?');
        $this->actingAs($sup)->get('/admin/stock')->assertOk()->assertSee('Ingresar calzado');

        $this->actingAs($sup)->post('/admin/stock/ingresar', [
            'product_id' => $p->id,
            'colors' => ['Negro', 'Café'],
            'tallas' => ['40', '41'],
            'q' => [0 => ['40' => '2', '41' => '3'], 1 => ['40' => '', '41' => '1']],
            'note' => 'factura 77',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(2, $this->stock($p, '40', 'Negro'));
        $this->assertSame(3, $this->stock($p, '41', 'Negro'));   // la talla 41 no existía: se creó
        $this->assertSame(1, $this->stock($p, '41', 'Café'));
        $this->assertSame(0, $this->stock($p, '40', 'Café'));
        $this->assertTrue(StockMovement::where('type', 'entrada')->where('reason', 'Ingreso manual: factura 77')->exists());

        // Se suma a lo que había
        $this->actingAs($sup)->post('/admin/stock/ingresar', ['product_id' => $p->id, 'colors' => ['negro'], 'tallas' => ['40'], 'q' => [0 => ['40' => '4']]])->assertRedirect();
        $this->assertSame(6, $this->stock($p, '40', 'Negro'));
    }

    public function test_supervisor_crea_un_modelo_nuevo_con_su_stock_en_una_sola_pantalla(): void
    {
        $sup = $this->supervisor('comercio');
        $this->actingAs($sup)->post('/admin/stock/ingresar', [
            'name' => 'botín roma', 'code' => 'br 77', 'category_id' => Category::where('slug', 'botines')->value('id'), 'price' => 480, 'cost' => 250,
            'colors' => ['Terracota'], 'tallas' => ['39', '41.5'], 'q' => [0 => ['39' => '2', '41.5' => '1']], 'show_web' => '1',
        ])->assertRedirect();

        $p = Product::where('code', 'BR-77')->firstOrFail();
        $this->assertSame('Botín Roma', $p->name);
        $this->assertEquals(250, $p->cost);
        $this->assertNotNull($p->published_at);
        $this->assertTrue($p->branches()->where('branches.id', $this->branch('comercio')->id)->exists());
        $this->assertSame(2, $this->stock($p, '39', 'Terracota'));
        $this->assertSame(1, $this->stock($p, '41.5', 'Terracota'));
    }

    public function test_ingreso_manual_avisa_errores_claros_y_respeta_la_tienda(): void
    {
        $sup = $this->supervisor('comercio');
        $p = $this->modelo();

        // Sin pares, sin color en un modelo con colores, código repetido, cantidades raras
        $this->actingAs($sup)->post('/admin/stock/ingresar', ['product_id' => $p->id, 'colors' => ['Negro'], 'tallas' => ['40'], 'q' => [0 => ['40' => '']]])->assertSessionHasErrors('q');
        $this->actingAs($sup)->post('/admin/stock/ingresar', ['product_id' => $p->id, 'tallas' => ['40'], 'q' => [0 => ['40' => '2']]])->assertSessionHasErrors('colors');
        $this->actingAs($sup)->post('/admin/stock/ingresar', ['name' => 'Otro', 'code' => 'MP-100', 'category_id' => $p->category_id, 'price' => 1, 'tallas' => ['40'], 'q' => [0 => ['40' => '1']]])->assertSessionHasErrors('code');
        $this->actingAs($sup)->post('/admin/stock/ingresar', ['product_id' => $p->id, 'colors' => ['Negro'], 'tallas' => ['40'], 'q' => [0 => ['40' => '-3']]])->assertSessionHasErrors('q');

        // El supervisor de Comercio no puede cargar en otra tienda aunque lo intente
        $this->actingAs($sup)->post('/admin/stock/ingresar', ['product_id' => $p->id, 'branch_id' => $this->branch('calacoto')->id, 'colors' => ['Negro'], 'tallas' => ['40'], 'q' => [0 => ['40' => '5']]])->assertRedirect();
        $this->assertSame(5, $this->stock($p, '40', 'Negro', 'comercio'));
        $this->assertSame(0, $this->stock($p, '40', 'Negro', 'calacoto'));

        // El admin elige la tienda; quien solo vende no puede ingresar stock
        $this->actingAs($this->admin())->post('/admin/stock/ingresar', ['product_id' => $p->id, 'branch_id' => $this->branch('calacoto')->id, 'colors' => ['Café'], 'tallas' => ['40'], 'q' => [0 => ['40' => '2']]])->assertRedirect();
        $this->assertSame(2, $this->stock($p, '40', 'Café', 'calacoto'));
        $this->actingAs($this->vendedor('comercio'))->get('/admin/stock/ingresar')->assertForbidden();
    }

    // ------------------------------------------------------------------ asistente

    public function test_asistente_no_acepta_respuestas_que_no_corresponden(): void
    {
        $sup = $this->supervisor('comercio');

        $r = $this->decir($sup, 'agregar un nuevo calzado');
        $this->assertStringContainsString('¿Cómo se llama', $r['text']);              // "calzado" no es un nombre
        $this->assertStringContainsString('Ingresar calzado', $r['links'][0][0]);     // siempre hay camino manual

        $this->decir($sup, 'botín roma');
        $r = $this->decir($sup, 'negro');                                               // "negro" no es un código
        $this->assertStringContainsString('Un código lleva números', $r['text']);
        $r = $this->decir($sup, 'no');                                                  // "no" = sin código, no cancelar
        $this->assertStringContainsString('precio', $r['text']);
        $r = $this->decir($sup, 'CH-500');                                              // un código no es un precio
        $this->assertStringContainsString('precio', $r['text']);
        $r = $this->decir($sup, '450');
        $r = $this->decir($sup, 'precio 380');                                          // corrige el precio, pero no responde la pregunta
        $this->assertStringContainsString('No entendí', $r['text']);
        $r = $this->decir($sup, 'hola');                                                // un saludo no rompe nada
        $this->assertStringContainsString('Seguimos', $r['text']);
        $this->decir($sup, 'negro y café');
        $this->decir($sup, 'del 39 al 41');
        $r = $this->decir($sup, '2');
        $this->assertTrue($r['confirm']);
        $rows = collect($r['table'][1])->pluck(1, 0);
        $this->assertSame('Bs. 380.00', $rows['Precio']);
        $this->assertSame('Negro, Café', $rows['Colores']);
        $this->assertSame('CH-500', $rows['Código']);
    }

    public function test_asistente_responde_consultas_sin_perder_lo_que_estaba_haciendo_y_reconoce_codigos_existentes(): void
    {
        $sup = $this->supervisor('comercio');
        $p = $this->modelo();

        $this->decir($sup, 'nuevo producto');
        $this->decir($sup, 'Botín Roma');
        $r = $this->decir($sup, '¿cuántos hay del MP-100?');
        $this->assertStringContainsString('Mocasín Penny', $r['text']);
        $this->assertStringContainsString('sigamos', $r['text']);

        // Un código que ya existe no es un producto nuevo: se sigue como entrada de pares
        $this->decir($sup, 'cancelar');
        $r = $this->decir($sup, 'nuevo mocasín código MP-100 tallas 40 negro 2 pares de cada uno');
        $this->assertStringContainsString('entrada de pares', $r['text']);
        $this->assertTrue($r['confirm']);
        $this->actingAs($sup)->postJson('/admin/asistente/confirmar')->assertOk();
        $this->assertSame(2, $this->stock($p, '40', 'Negro'));

        $r = $this->decir($sup, 'hola');
        $this->assertStringContainsString('Hola', $r['text']);
        $this->assertNotEmpty($r['links']);
    }

    public function test_los_chats_del_asistente_quedan_para_el_admin_aunque_se_limpie_la_pantalla(): void
    {
        $sup = $this->supervisor('comercio');
        $sup->update(['name' => 'Rosa Quispe']);
        $this->decir($sup, 'hola');
        $this->actingAs($sup)->delete('/admin/asistente')->assertRedirect();

        $this->assertSame(2, AssistantMessage::where('user_id', $sup->id)->whereNotNull('hidden_at')->count());
        $this->actingAs($sup)->get('/admin/asistente')->assertOk()->assertDontSee('¡Hola, ');

        $this->actingAs($this->admin())->get('/admin/asistente/equipo?usuario='.$sup->id)->assertOk()
            ->assertSee($sup->name)->assertSee('oculto en su pantalla');
        $this->actingAs($this->supervisor('calacoto'))->get('/admin/asistente/equipo')->assertOk()->assertDontSee($sup->name);
        $this->actingAs($this->vendedor('comercio'))->get('/admin/asistente/equipo')->assertForbidden();
    }
}
