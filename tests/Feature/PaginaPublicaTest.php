<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerTask;
use App\Models\Product;
use App\Models\RestockRequest;
use App\Services\PurchaseService;
use App\Support\ImageUpload;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Página pública: filtro por talla/color, avísame cuando llegue, guía de tallas, catálogo para Meta/Google y fotos livianas. */
class PaginaPublicaTest extends TestCase
{
    private function producto(string $name, string $code, array $sizes, string $color = 'Negro'): Product
    {
        $p = Product::create([
            'category_id' => Category::where('slug', 'botines')->value('id'), 'name' => $name, 'code' => $code,
            'slug' => \Illuminate\Support\Str::slug($name), 'base_price' => 450, 'is_active' => true, 'published_at' => now(),
        ]);
        $p->images()->create(['path' => 'uploads/products/x.jpg', 'is_primary' => true]);
        if ($sizes) {
            PurchaseService::register($this->admin(), $this->branch('calacoto'), null, [['product' => $p, 'color' => $color, 'sizes' => $sizes, 'unit_cost' => 200]]);
        } else {
            $p->branches()->attach($this->branch('calacoto')->id, ['is_active' => true]);
        }

        return $p;
    }

    public function test_catalogo_filtra_por_talla_y_color_con_stock(): void
    {
        $this->producto('Botín Chelsea', 'CH-210', ['40' => 2, '41' => 1]);
        $this->producto('Mocasín Roma', 'MR-10', ['42' => 1], 'Café');

        $this->get('/calacoto/catalogo')->assertOk()->assertSee('Tu talla')->assertSee('Café');
        $this->get('/calacoto/catalogo?talla=40')->assertSee('Botín Chelsea')->assertDontSee('Mocasín Roma');
        $this->get('/calacoto/catalogo?talla=42')->assertSee('Mocasín Roma')->assertDontSee('Botín Chelsea');
        $this->get('/calacoto/catalogo?color=Café')->assertSee('Mocasín Roma')->assertDontSee('Botín Chelsea');
        $this->get('/calacoto/catalogo?talla=44')->assertSee('Sin resultados');
    }

    public function test_avisame_crea_cliente_y_seguimiento_cuando_llega_la_talla(): void
    {
        $p = $this->producto('Botín Chelsea', 'CH-210', ['40' => 1]);
        $sup = $this->supervisor('calacoto');

        $this->get("/calacoto/producto/{$p->slug}")->assertOk()->assertSee('Guía de tallas')->assertSee('Avísame cuando llegue');
        $this->post("/calacoto/producto/{$p->slug}/avisame", ['name' => 'Ana', 'phone' => '7001 1222', 'size' => '42', 'color' => 'Negro'])
            ->assertRedirect()->assertSessionHas('restock');
        $this->post("/calacoto/producto/{$p->slug}/avisame", ['name' => 'Ana', 'phone' => '7001 1222', 'size' => '42', 'color' => 'Negro']);   // repetido
        $this->post("/calacoto/producto/{$p->slug}/avisame", ['name' => 'X', 'phone' => '123'])->assertSessionHasErrors('phone');

        $ana = Customer::where('phone', '70011222')->firstOrFail();
        $this->assertSame(1, RestockRequest::count());
        $this->assertSame('interesado', $ana->stage);
        $this->actingAs($sup)->get('/admin/crm')->assertSee('Esperan su talla')->assertSee('Botín Chelsea talla 42 Negro');

        // Llega otra talla: nada. Llega la 42 negra: seguimiento para el supervisor de la tienda
        PurchaseService::register($this->admin(), $this->branch('calacoto'), null, [['product' => $p, 'color' => 'Negro', 'sizes' => ['41' => 1], 'unit_cost' => 200]]);
        $this->assertSame(0, CustomerTask::count());
        PurchaseService::register($this->admin(), $this->branch('calacoto'), null, [['product' => $p, 'color' => 'Negro', 'sizes' => ['42' => 2], 'unit_cost' => 200]]);
        $task = CustomerTask::firstOrFail();
        $this->assertSame($ana->id, $task->customer_id);
        $this->assertTrue($task->user->hasRole(\App\Models\Role::SUPERVISOR) && $task->user->branch_id === $sup->branch_id, 'Lo recibe un supervisor de esa tienda');
        $this->assertStringContainsString('talla 42', $task->title);
        $this->assertNotNull(RestockRequest::first()->notified_at);
    }

    public function test_catalogo_xml_para_facebook_y_google(): void
    {
        $p = $this->producto('Botín Chelsea', 'CH-210', ['40' => 2]);
        $sinFoto = $this->producto('Sin Foto', 'SF-1', ['40' => 1]);
        $sinFoto->images()->delete();

        $xml = $this->get('/catalogo.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc, 'XML válido');
        $items = $doc->channel->item;
        $this->assertCount(1, $items);
        $g = $items[0]->children('http://base.google.com/ns/1.0');
        $this->assertSame('CH-210', (string) $g->item_group_id);
        $this->assertSame('in stock', (string) $g->availability);
        $this->assertSame('450.00 BOB', (string) $g->price);
        $this->assertSame('40', (string) $g->size);
        $this->assertStringContainsString("/calacoto/producto/{$p->slug}", (string) $g->link);
    }

    public function test_fotos_grandes_se_achican_al_subir(): void
    {
        $file = UploadedFile::fake()->image('celular.jpg', 3200, 2400);
        $path = ImageUpload::store($file, 'products');
        [$w, $h] = getimagesize(public_path($path));
        $this->assertSame([1600, 1200], [$w, $h]);

        $small = ImageUpload::store(UploadedFile::fake()->image('chica.png', 400, 300), 'products');
        $this->assertSame([400, 300], array_slice(getimagesize(public_path($small)), 0, 2));
    }

    public function test_pagina_de_la_tienda_tiene_como_llegar(): void
    {
        $this->get('/calacoto')->assertOk()->assertSee('Cómo llegar')->assertSee('google.com/maps/dir', false);
    }
}
