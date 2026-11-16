<?php

namespace Tests\Feature;

use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\SpecField;
use App\Models\Stock;
use Tests\TestCase;

/** Panel: categorías/clases, productos (fotos, ficha técnica, tiendas), variantes y stock. */
class PanelCatalogoTest extends TestCase
{
    private function crearProducto($user, array $extra = []): Product
    {
        $botines = Category::where('slug', 'botines')->first();
        $campo = SpecField::where('name', 'Suela')->first();

        $this->actingAs($user)->post('/admin/productos', [
            'name' => 'Botín de prueba',
            'category_id' => $botines->id,
            'base_price' => 450,
            'compare_at_price' => 520,
            'short_description' => 'Cuero legítimo',
            'material' => 'Cuero',
            'origin_country' => 'Brasil',
            'published' => 1,
            'is_active' => 1,
            'is_featured' => 1,
            'images' => [$this->foto('cafe.jpg'), $this->foto('negro.png')],
            'spec' => [$campo->id => 'Goma antideslizante'],
            ...$extra,
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Product::where('name', 'Botín de prueba')->latest('id')->firstOrFail();
    }

    public function test_paginas_del_catalogo_cargan(): void
    {
        $admin = $this->admin();
        foreach (['/admin', '/admin/productos', '/admin/productos/create', '/admin/categorias', '/admin/categorias/create', '/admin/stock', '/admin/stock/global', '/admin/colores'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_crear_editar_y_borrar_clase_de_calzado(): void
    {
        $admin = $this->admin();
        $calzado = Category::where('slug', 'calzado')->first();

        $this->actingAs($admin)->post('/admin/categorias', ['name' => 'Mocasines de lujo', 'parent_id' => $calzado->id, 'is_active' => 1])
            ->assertRedirect('/admin/categorias');
        $clase = Category::where('name', 'Mocasines de lujo')->firstOrFail();
        $this->assertSame($calzado->id, $clase->parent_id);

        $this->actingAs($admin)->put("/admin/categorias/{$clase->id}", ['name' => 'Mocasines premium', 'parent_id' => $calzado->id, 'is_active' => 1])
            ->assertRedirect();
        $this->assertSame('Mocasines premium', $clase->fresh()->name);

        $this->actingAs($admin)->delete("/admin/categorias/{$clase->id}")->assertRedirect();
        $this->assertNull(Category::find($clase->id));
    }

    public function test_crear_producto_con_fotos_ficha_y_tiendas(): void
    {
        $admin = $this->admin();
        $product = $this->crearProducto($admin, ['branches' => [$this->branch('calacoto')->id, $this->branch('comercio')->id]]);

        $this->assertCount(2, $product->images);
        $product->images->each(fn ($img) => $this->assertFotoGuardada($img->path));
        $this->assertTrue($product->images->contains('is_primary', true));
        $this->assertSame('Goma antideslizante', $product->specs->first()->value);
        $this->assertEqualsCanonicalizing(['calacoto', 'comercio'], $product->branches->pluck('slug')->all());

        // Se ve en las tiendas donde se vende, no en las otras
        $this->get("/calacoto/producto/{$product->slug}")->assertOk()->assertSee('Botín de prueba')->assertSee('Goma antideslizante');
        $this->get('/calacoto/catalogo')->assertSee('Botín de prueba');
        $this->get('/el-alto/catalogo')->assertDontSee('Botín de prueba');
    }

    public function test_foto_falsa_se_rechaza_con_mensaje(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/productos', [
            'name' => 'Con foto falsa', 'category_id' => Category::where('slug', 'botines')->value('id'),
            'base_price' => 100, 'images' => [$this->fotoFalsa()],
        ])->assertSessionHasErrors('images.0');
        $this->assertNull(Product::where('name', 'Con foto falsa')->first());
    }

    public function test_editar_producto_agregar_principal_y_quitar_fotos(): void
    {
        $admin = $this->admin();
        $product = $this->crearProducto($admin);

        $this->actingAs($admin)->put("/admin/productos/{$product->id}", [
            'name' => 'Botín de prueba editado', 'category_id' => $product->category_id, 'base_price' => 399,
            'published' => 1, 'is_active' => 1, 'branches' => [$this->branch('el-alto')->id],
        ])->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame('399.00', $product->base_price);
        $this->assertSame(['el-alto'], $product->branches->pluck('slug')->all());

        $this->actingAs($admin)->post("/admin/productos/{$product->id}/imagenes", ['images' => [$this->foto('extra.webp')]])->assertRedirect();
        $nueva = $product->images()->reorder()->latest('id')->first();
        $this->assertFotoGuardada($nueva->path);

        $this->actingAs($admin)->post("/admin/imagenes/{$nueva->id}/principal")->assertRedirect();
        $this->assertTrue($nueva->fresh()->is_primary);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());

        // Volver a marcar la que ya es principal no debe dejar el producto sin foto principal
        $this->actingAs($admin)->post("/admin/imagenes/{$nueva->id}/principal")->assertRedirect();
        $this->assertTrue($nueva->fresh()->is_primary);

        $this->actingAs($admin)->delete("/admin/imagenes/{$nueva->id}")->assertRedirect();
        $this->assertFileDoesNotExist(public_path($nueva->path));
        $this->assertTrue($product->images()->where('is_primary', true)->exists(), 'Otra foto debe quedar como principal');
    }

    public function test_variantes_stock_por_grilla_movimientos_y_kardex(): void
    {
        $admin = $this->admin();
        $product = $this->crearProducto($admin, ['branches' => [$this->branch('calacoto')->id]]);
        $negro = AttributeValue::where('value', 'Negro')->first();
        $tallas = AttributeValue::whereIn('value', ['40', '41'])->pluck('id')->all();

        $this->actingAs($admin)->post("/admin/productos/{$product->id}/variantes/generar", ['colores' => [$negro->id], 'tallas' => $tallas])
            ->assertRedirect();
        $this->assertSame(2, $product->variants()->count());

        $variants = $product->variants()->pluck('id');
        $this->actingAs($admin)->post("/admin/productos/{$product->id}/variantes/stock", [
            'branch_id' => $this->branch('calacoto')->id,
            'qty' => [$variants[0] => 5, $variants[1] => 2],
            'min' => [$variants[0] => 1, $variants[1] => 1],
        ])->assertRedirect();
        $stock = Stock::where('product_variant_id', $variants[0])->where('branch_id', $this->branch('calacoto')->id)->first();
        $this->assertSame(5, $stock->quantity);

        $this->actingAs($admin)->post("/admin/stock/{$stock->id}/movimiento", ['type' => 'entrada', 'quantity' => 3, 'reason' => 'Llegó pedido'])->assertRedirect();
        $this->actingAs($admin)->post("/admin/stock/{$stock->id}/movimiento", ['type' => 'salida', 'quantity' => 1])->assertRedirect();
        $this->assertSame(7, $stock->fresh()->quantity);
        $this->actingAs($admin)->post("/admin/stock/{$stock->id}/movimiento", ['type' => 'salida', 'quantity' => 50])->assertSessionHasErrors('quantity');

        $this->actingAs($admin)->get("/admin/stock/{$stock->id}/kardex")->assertOk()->assertSee('Llegó pedido');
        $this->actingAs($admin)->get('/admin/stock/global')->assertOk();
        $this->get("/calacoto/producto/{$product->slug}")->assertOk();
    }

    public function test_borrar_producto(): void
    {
        $admin = $this->admin();
        $product = $this->crearProducto($admin);
        $this->actingAs($admin)->delete("/admin/productos/{$product->id}")->assertRedirect('/admin/productos');
        $this->assertSoftDeleted($product);
    }

    public function test_supervisor_crea_productos_solo_en_su_tienda(): void
    {
        $sup = $this->supervisor('calacoto');
        $product = $this->crearProducto($sup, ['branches' => [$this->branch('el-alto')->id]]);
        $this->assertSame(['calacoto'], $product->branches->pluck('slug')->all(), 'El supervisor siempre publica en su propia tienda');

        $otro = $this->crearProducto($this->admin(), ['branches' => [$this->branch('el-alto')->id]]);
        $this->actingAs($sup)->get("/admin/productos/{$otro->id}/edit")->assertForbidden();
        $this->actingAs($sup)->get('/admin/stock/global')->assertForbidden();
        $this->actingAs($sup)->get('/admin/stock')->assertOk();
    }

    public function test_vendedor_no_administra_catalogo(): void
    {
        $vend = $this->vendedor('calacoto');
        $this->actingAs($vend)->get('/admin/productos')->assertForbidden();
        $this->actingAs($vend)->get('/admin/categorias')->assertForbidden();
        $this->actingAs($vend)->get('/admin/stock')->assertOk();
        $this->actingAs($vend)->get('/admin/usuarios')->assertForbidden();
    }
}
