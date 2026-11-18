<?php

namespace Tests\Feature;

use App\Assistant\Numbers;
use App\Assistant\Parser;
use App\Models\AssistantMessage;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Services\VariantBuilder;
use Tests\TestCase;

/** Asistente del panel: entiende órdenes en español, pregunta lo que falta y respeta los roles. */
class AsistenteTest extends TestCase
{
    private function decir(User $user, string $text, array $images = []): array
    {
        return $this->actingAs($user)->post('/admin/asistente/mensaje', ['text' => $text, 'images' => $images], ['Accept' => 'application/json'])
            ->assertOk()->json();
    }

    private function confirmar(User $user): array
    {
        return $this->actingAs($user)->postJson('/admin/asistente/confirmar')->assertOk()->json();
    }

    private function chelsea(string $branch = 'calacoto'): Product
    {
        $p = Product::create([
            'category_id' => Category::where('slug', 'botines')->value('id'), 'name' => 'Botín Chelsea', 'code' => 'CH-210',
            'slug' => 'botin-chelsea', 'base_price' => 450, 'is_active' => true, 'published_at' => now(),
        ]);
        $p->branches()->attach($this->branch($branch)->id, ['is_active' => true]);
        $p->images()->create(['path' => 'uploads/products/a.jpg', 'is_primary' => true, 'sort_order' => 1]);
        $p->images()->create(['path' => 'uploads/products/b.jpg', 'is_primary' => false, 'sort_order' => 2]);
        VariantBuilder::generate($p, [VariantBuilder::value('color', 'Negro')->id, VariantBuilder::value('color', 'Café')->id],
            [VariantBuilder::value('talla', '40')->id, VariantBuilder::value('talla', '41')->id]);

        return $p->fresh();
    }

    private function stock(Product $p, string $talla, string $color, string $branch = 'calacoto'): int
    {
        $v = $p->variants()->with('attributeValues.attribute')->get()->first(fn ($v) => $v->valueOf('talla') === $talla && $v->valueOf('color') === $color);

        return (int) Stock::where('product_variant_id', $v->id)->where('branch_id', $this->branch($branch)->id)->value('quantity');
    }

    public function test_entiende_numeros_codigos_tallas_colores_y_precios(): void
    {
        $this->assertSame('precio 450 bolivianos, 3 pares de cada uno, tallas 38 al 42', Numbers::toDigits('precio cuatrocientos cincuenta bolivianos, tres pares de cada uno, tallas treinta y ocho al cuarenta y dos'));

        $p = new Parser('Nuevo botín Chelsea código CH 210, colores café y negro, tallas del 38 al 42, 3 pares de cada uno, precio 450 en Calacoto');
        $this->assertSame('nuevo_producto', $p->intent());
        $this->assertSame('Botín Chelsea', $p->productName());
        $this->assertSame('CH-210', $p->code());
        $this->assertSame(['38', '39', '40', '41', '42'], $p->tallas());
        $this->assertEqualsCanonicalizing(['Café', 'Negro'], $p->colors());
        $this->assertSame(3, $p->quantityEach());
        $this->assertSame(450.0, $p->price());
        $this->assertSame([$this->branch('calacoto')->id], $p->branches());
        $this->assertSame('botines', $p->category()->slug);

        $this->assertNull((new Parser('el precio es 480'))->code(), '"es 480" no es un código');
        $this->assertSame('CH-210', (new Parser('¿cuántos ch210 quedan?'))->code());
        $this->assertSame('venta', (new Parser('vendí 2 del CH-210 talla 41 café'))->intent());
        $this->assertSame('salida', (new Parser('saca 2 pares del CH-210 talla 41 café'))->intent());
        $this->assertSame(2, (new Parser('vendí 2 del CH-210 talla 41 café'))->quantity());
        $this->assertSame('entrada', (new Parser('llegaron 5 pares del CH-210'))->intent());
        $this->assertSame('consulta', (new Parser('¿Cuántos CH-210 talla 40 hay?'))->intent());
        $this->assertSame('mocasines', (new Parser('mocasines'))->category()->slug);
        $this->assertSame('de-traje', (new Parser('zapato de vestir'))->category()->slug);
    }

    public function test_admin_registra_producto_completo_en_una_sola_orden(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/asistente')->assertOk()->assertSee('Asistente');

        $r = $this->decir($admin, 'Nuevo botín Chelsea código CH-210, de cuero liso, colores café y negro, tallas del 38 al 42, 3 pares de cada uno, precio 450, en Calacoto');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->assertSame('Botín Chelsea', $r['table'][1][0][1]);
        $this->assertNull(Product::where('code', 'CH-210')->first(), 'Nada se guarda antes de confirmar');

        $r = $this->confirmar($admin);
        $this->assertStringContainsString('registré', $r['text']);
        $p = Product::where('code', 'CH-210')->firstOrFail();
        $this->assertSame('Botín Chelsea', $p->name);
        $this->assertSame('Cuero liso', $p->material);
        $this->assertSame('450.00', $p->base_price);
        $this->assertSame(10, $p->variants()->count());
        $this->assertSame(['calacoto'], $p->branches->pluck('slug')->all());
        $this->assertSame(3, $this->stock($p, '40', 'Negro'));
        $this->assertSame(30, (int) Stock::whereIn('product_variant_id', $p->variants()->pluck('id'))->sum('quantity'));
        $this->get("/calacoto/producto/{$p->slug}")->assertOk()->assertSee('Botín Chelsea');

        // Queda registro de quién ordenó qué
        $this->assertTrue(AssistantMessage::where('user_id', $admin->id)->where('role', 'user')->where('text', 'like', 'Nuevo botín%')->exists());
    }

    public function test_pregunta_lo_que_falta_paso_a_paso(): void
    {
        $admin = $this->admin();
        $this->assertStringContainsString('código', $this->decir($admin, 'nuevo mocasín Roma')['text']);
        $this->assertStringContainsString('precio', $this->decir($admin, 'sin código')['text']);
        $this->assertStringContainsString('colores', $this->decir($admin, 'cuatrocientos')['text']);
        $this->assertStringContainsString('tallas', $this->decir($admin, 'negro')['text']);
        $this->assertStringContainsString('tienda', $this->decir($admin, 'del 39 al 41')['text']);
        $this->assertStringContainsString('pares', $this->decir($admin, 'en todas')['text']);
        $r = $this->decir($admin, '2');
        $this->assertTrue($r['confirm']);

        // Corregir antes de confirmar
        $r = $this->decir($admin, 'no, el precio es 420');
        $this->assertTrue($r['confirm']);
        $this->assertStringContainsString('420', collect($r['table'][1])->firstWhere(0, 'Precio')[1]);

        $this->decir($admin, 'sí');
        $p = Product::where('name', 'Mocasín Roma')->firstOrFail();
        $this->assertNull($p->code);
        $this->assertSame('420.00', $p->base_price);
        $this->assertSame('mocasines', $p->category->slug);
        $this->assertSame(3, $p->variants()->count());
        $this->assertCount(3, $p->branches);
        $this->assertSame(2, $this->stock($p, '40', 'Negro', 'el-alto'));
    }

    public function test_entradas_y_salidas_de_stock(): void
    {
        $p = $this->chelsea();
        $sup = $this->supervisor('calacoto');

        $r = $this->decir($sup, 'Llegaron 5 pares del CH-210 talla 40 negro');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->confirmar($sup);
        $this->assertSame(5, $this->stock($p, '40', 'Negro'));

        // Venta: falta el color → lo pregunta; luego el cliente ("no" = sin cliente, no cancela)
        $this->assertStringContainsString('color', $this->decir($sup, 'vendí 2 del CH-210 talla 40')['text']);
        $this->assertStringContainsString('nombre de quién', $this->decir($sup, 'negro')['text']);
        $r = $this->decir($sup, 'no');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->decir($sup, 'confirmo');
        $this->assertSame(3, $this->stock($p, '40', 'Negro'));
        $this->assertSame(1, \App\Models\Sale::count());

        // Salida que no es venta (ej. devolución al proveedor)
        $this->decir($sup, 'saca 1 par del CH-210 talla 40 negro');
        $this->decir($sup, 'sí');
        $this->assertSame(2, $this->stock($p, '40', 'Negro'));

        // No deja vender más de lo que hay
        $r = $this->decir($sup, 'vendí 9 del CH-210 talla 40 negro');
        $this->assertStringContainsString('No alcanza', $r['text']);
        $this->decir($sup, 'cancelar');
        $this->assertSame(2, $this->stock($p, '40', 'Negro'));
    }

    public function test_consultar_stock_por_codigo_nombre_y_clase(): void
    {
        $p = $this->chelsea();
        Stock::where('product_variant_id', $p->variants()->first()->id)->update(['quantity' => 4]);
        $vend = $this->vendedor('calacoto');

        $r = $this->decir($vend, '¿Cuántos CH-210 hay?');
        $this->assertStringContainsString('Hay **4**', $r['text']);
        $this->assertNotNull($r['table']);

        $r = $this->decir($vend, 'cuántos chelsea quedan');
        $this->assertStringContainsString('Botín Chelsea', $r['text']);

        $r = $this->decir($vend, 'stock de botines');
        $this->assertStringContainsString('Botines', $r['text']);
        $this->assertSame('Botín Chelsea (CH-210)', $r['table'][1][0][0]);

        $r = $this->decir($vend, '¿cuántos XY-999 hay?');
        $this->assertStringContainsString('No encontré', $r['text']);
    }

    public function test_respeta_los_roles(): void
    {
        $vend = $this->vendedor('calacoto');
        $r = $this->decir($vend, 'Nuevo botín Prueba código PR-100 precio 300');
        $this->assertStringContainsString('no permite', $r['text']);
        $this->assertNull(Product::where('code', 'PR-100')->first());

        $this->chelsea();
        $this->assertStringContainsString('no permite', $this->decir($vend, 'llegaron 3 pares del CH-210 talla 40 negro')['text']);
        $this->assertStringContainsString('no permite', $this->decir($vend, 'publica el CH-210')['text']);

        // Supervisor: siempre en su tienda, no pregunta la tienda
        $sup = $this->supervisor('el-alto');
        $r = $this->decir($sup, 'nuevo zapatenis Sport código ZS-01 color blanco tallas 40 y 41, 1 par de cada uno, precio 300 en Calacoto');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->confirmar($sup);
        $this->assertSame(['el-alto'], Product::where('code', 'ZS-01')->first()->branches->pluck('slug')->all());

        // Producto de otra tienda: no puede moverle stock
        $this->assertStringContainsString('no se vende en tu tienda', $this->decir($sup, 'llegaron 2 pares del CH-210 talla 40 negro')['text']);
    }

    public function test_publicar_con_fotos_adjuntas_y_cancelar_borra_las_fotos(): void
    {
        $p = $this->chelsea();
        $admin = $this->admin();

        $r = $this->decir($admin, 'publica el CH-210', [$this->foto('x.jpg'), $this->foto('y.jpg')]);
        $this->assertStringContainsString('carrusel', $r['text']);
        $r = $this->decir($admin, 'separadas');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->confirmar($admin);
        $posts = Post::where('product_id', $p->id)->get();
        $this->assertCount(2, $posts);
        $posts->each(fn ($post) => $this->assertFotoGuardada($post->media->first()->path));
        $this->assertStringContainsString('Bs. 450.00', $posts->first()->body);

        // Fotos adjuntas y luego cancelar: los archivos se borran
        $before = glob(public_path('uploads/products/*'));
        $this->decir($admin, 'publica el CH-210 en carrusel', [$this->foto('z.jpg')]);
        $nuevas = array_values(array_diff(glob(public_path('uploads/products/*')), $before));
        $this->assertCount(1, $nuevas);
        $this->decir($admin, 'cancelar');
        $this->assertFileDoesNotExist($nuevas[0]);
        $this->assertSame(2, Post::where('product_id', $p->id)->count());
    }

    public function test_codigo_del_producto_en_el_formulario(): void
    {
        $admin = $this->admin();
        $base = ['category_id' => Category::where('slug', 'botines')->value('id'), 'base_price' => 100, 'is_active' => 1];
        $this->actingAs($admin)->post('/admin/productos', $base + ['name' => 'Con código', 'code' => ' ch 210 '])->assertSessionHasNoErrors();
        $this->assertSame('CH-210', Product::where('name', 'Con código')->value('code'));
        $this->actingAs($admin)->post('/admin/productos', $base + ['name' => 'Repetido', 'code' => 'CH-210'])->assertSessionHasErrors(['code' => 'Ya hay otro producto con ese código.']);
        $this->actingAs($admin)->get('/admin/productos?q=CH-210')->assertSee('Con código');
    }
}
