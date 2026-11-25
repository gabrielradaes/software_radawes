<?php

namespace Tests\Feature;

use App\Models\AssistantExample;
use App\Models\AssistantWord;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Stock;
use App\Services\VariantBuilder;
use Tests\TestCase;

/** El equipo le enseña al asistente (palabras y frases) y el asistente hace también apartados, caja y publicaciones libres. */
class EnsenarAsistenteTest extends TestCase
{
    private function decir($user, string $text): array
    {
        return $this->actingAs($user)->postJson('/admin/asistente/mensaje', ['text' => $text])->assertOk()->json();
    }

    private function confirmar($user): array
    {
        return $this->actingAs($user)->postJson('/admin/asistente/confirmar')->assertOk()->json();
    }

    private function modeloConStock(int $qty = 3): Product
    {
        $p = Product::create(['category_id' => Category::where('slug', 'botines')->value('id'), 'name' => 'Botín Chelsea', 'code' => 'CH-210',
            'slug' => 'botin-chelsea', 'base_price' => 450, 'is_active' => true, 'published_at' => now()]);
        $p->branches()->attach($this->branch('comercio')->id, ['is_active' => true]);
        $v = VariantBuilder::generate($p, [VariantBuilder::value('color', 'Negro')->id], [VariantBuilder::value('talla', '40')->id])->first();
        Stock::where('product_variant_id', $v->id)->where('branch_id', $this->branch('comercio')->id)->first()->move('entrada', $qty);

        return $p->fresh();
    }

    public function test_aprende_una_frase_cuando_tocan_que_querias_hacer(): void
    {
        $sup = $this->supervisor('comercio');

        $r = $this->decir($sup, 'méteme esto al almacén');
        $this->assertStringContainsString('No te entendí', $r['text']);
        $this->assertContains('entrada', array_column($r['choices'], 1));

        $r = $this->actingAs($sup)->postJson('/admin/asistente/aprender', ['intent' => 'entrada'])->assertOk()->json();
        $this->assertStringContainsString('ya lo aprendí', $r['text']);
        $this->assertTrue(AssistantExample::where('intent', 'entrada')->where('source', 'boton')->exists());

        // Otra forma parecida ya se entiende, con datos distintos
        $this->decir($sup, 'cancelar');
        $r = $this->decir($sup, 'méteme estos zapatos al almacén');
        $this->assertStringContainsString('¿De qué producto?', $r['text']);

        // No se puede "enseñar" una acción que su rol no hace
        $this->actingAs($this->vendedor('comercio'))->postJson('/admin/asistente/aprender', ['intent' => 'traspaso'])->assertStatus(422);
    }

    public function test_pantalla_ensenar_palabras_frases_y_probar(): void
    {
        $sup = $this->supervisor('comercio');
        $urbanos = Category::where('name', 'like', 'Casuales%')->value('id') ?? Category::whereNotNull('parent_id')->value('id');

        $this->actingAs($sup)->post('/admin/asistente/ensenar/palabras', ['kind' => 'color', 'phrase' => 'Tabacão', 'meaning' => 'tabaco'])->assertRedirect();
        $this->actingAs($sup)->post('/admin/asistente/ensenar/palabras', ['kind' => 'marca', 'phrase' => 'democrata', 'meaning' => 'Demócrata'])->assertRedirect();
        $this->actingAs($sup)->post('/admin/asistente/ensenar/palabras', ['kind' => 'clase', 'phrase' => 'urbano', 'category_id' => $urbanos])->assertRedirect();
        $this->actingAs($sup)->post('/admin/asistente/ensenar/palabras', ['kind' => 'palabra', 'phrase' => 'almacén', 'meaning' => 'stock'])->assertRedirect();
        $this->actingAs($sup)->post('/admin/asistente/ensenar/frases', ['text' => 'súbeme estos al sistema', 'intent' => 'entrada'])->assertRedirect();
        $this->assertSame(4, AssistantWord::count() - 4);   // 4 que ya trae + 4 nuevas

        $this->actingAs($sup)->get('/admin/asistente/ensenar?probar='.urlencode('súbeme 2 tabacão 39 del 596101-001 democrata urbano'))->assertOk()
            ->assertSee('Ingresar calzado')->assertSee('596101-001')->assertSee('Tabaco')->assertSee('Demócrata');

        // El asistente usa lo enseñado
        $r = $this->decir($sup, 'agregar 596101-001 tabacão talla 39 democrata urbano');
        $this->assertStringContainsString('Demócrata 596101-001', $r['text']);

        // Cada quien quita lo suyo; lo ajeno solo el admin
        $word = AssistantWord::where('phrase', 'urbano')->first();
        $this->actingAs($this->supervisor('calacoto'))->delete('/admin/asistente/ensenar/palabras/'.$word->id)->assertForbidden();
        $this->actingAs($sup)->delete('/admin/asistente/ensenar/palabras/'.$word->id)->assertRedirect();
        $this->actingAs($this->vendedor('comercio'))->get('/admin/asistente/ensenar')->assertForbidden();
    }

    public function test_publica_texto_con_color_sin_producto(): void
    {
        $sup = $this->supervisor('comercio');
        $r = $this->decir($sup, 'publica: llegó colección nueva de botines, fondo rojo');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->confirmar($sup);

        $post = Post::latest('id')->first();
        $this->assertSame('Llegó colección nueva de botines', $post->title);
        $this->assertSame('#b91c1c', $post->bg);
        $this->assertSame($this->branch('comercio')->id, $post->branch_id);
        $this->assertNull($post->product_id);
    }

    public function test_aparta_un_par_para_un_cliente(): void
    {
        $sup = $this->supervisor('comercio');
        $p = $this->modeloConStock(3);

        $r = $this->decir($sup, 'aparta el CH-210 talla 40 negro para Carlos Mamani celular 70011222');
        $this->assertStringContainsString('adelanto', $r['text']);
        $r = $this->decir($sup, '100');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->confirmar($sup);

        $res = Reservation::latest('id')->firstOrFail();
        $this->assertEquals(100, $res->deposit);
        $this->assertSame('Carlos Mamani', $res->customer->name);
        $this->assertSame(1, (int) Stock::whereIn('product_variant_id', $p->variants()->pluck('id'))->where('branch_id', $this->branch('comercio')->id)->value('reserved'));
    }

    public function test_caja_abrir_gasto_ver_y_cerrar(): void
    {
        $sup = $this->supervisor('comercio');
        $this->assertTrue($this->decir($sup, 'abre la caja con 200')['confirm']);
        $this->confirmar($sup);
        $this->assertNotNull(CashSession::openFor($this->branch('comercio')->id));

        $r = $this->decir($sup, 'gasté 15 bs en pasaje');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->confirmar($sup);

        $this->assertStringContainsString('Bs 185,00', $this->decir($sup, '¿cuánto hay en caja?')['text']);

        $this->decir($sup, 'cierra la caja');
        $r = $this->decir($sup, '185');
        $this->assertTrue($r['confirm']);
        $this->assertStringContainsString('cuadra perfecto', $this->confirmar($sup)['text']);
        $this->assertNull(CashSession::openFor($this->branch('comercio')->id));
    }
}
