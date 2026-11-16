<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Post;
use App\Models\Product;
use Tests\TestCase;

/** Sitio público: tiendas, catálogo, producto, novedades, SEO, clientes (Gmail + código), me gusta y comentarios. */
class SitioPublicoTest extends TestCase
{
    private function producto(): Product
    {
        $p = Product::create([
            'category_id' => \App\Models\Category::where('slug', 'botines')->value('id'),
            'name' => 'Botín Chelsea', 'slug' => 'botin-chelsea', 'base_price' => 490,
            'is_active' => true, 'published_at' => now()->subDay(),
        ]);
        $p->branches()->attach($this->branch('calacoto')->id, ['is_active' => true]);

        return $p;
    }

    public function test_paginas_publicas_cargan(): void
    {
        $p = $this->producto();
        $post = Post::create(['user_id' => $this->admin()->id, 'branch_id' => $this->branch('calacoto')->id, 'type' => 'texto', 'title' => 'Novedad', 'body' => 'x', 'published_at' => now()]);

        foreach (['/', '/comercio', '/calacoto', '/el-alto', '/calacoto/catalogo', '/calacoto/catalogo/calzado', '/calacoto/catalogo/botines?orden=precio-asc&oferta=1&q=bot',
                  "/calacoto/producto/{$p->slug}", '/calacoto/novedades', "/calacoto/novedades/{$post->id}", '/calacoto/registro', '/calacoto/ingresar', '/calacoto/recuperar', '/admin/login', '/sitemap.xml'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/no-existe-esta-tienda')->assertNotFound();
        $this->get('/sitemap.xml')->assertSee("/calacoto/producto/{$p->slug}", false);
        $this->get('/calacoto')->assertSee('application/ld+json', false);

        // Producto sin descripción corta: la página se arma completa y Google recibe una descripción
        $this->get("/calacoto/producto/{$p->slug}")->assertSee('<meta name="description" content="Botín Chelsea"', false);
    }

    public function test_dominio_de_marca_redirige_a_su_tienda(): void
    {
        $this->get('http://radawes.localhost/')->assertRedirect('http://radawes.localhost/calacoto');
    }

    public function test_registro_solo_gmail_y_verificacion_por_codigo(): void
    {
        $datos = ['name' => 'Ana Pérez', 'phone' => '70011333', 'city' => 'La Paz', 'password' => 'secreto1', 'password_confirmation' => 'secreto1', 'terms' => 1];

        $this->post('/calacoto/registro', $datos + ['email' => 'ana@hotmail.com'])->assertSessionHasErrors('email');

        $this->post('/calacoto/registro', $datos + ['email' => 'Ana.Perez@Gmail.com'])->assertRedirect('/calacoto/verificar');
        $c = Customer::where('email', 'ana.perez@gmail.com')->firstOrFail();
        $this->assertNull($c->email_verified_at);
        $code = $this->ultimoCodigo('ana.perez@gmail.com');

        $this->post('/calacoto/verificar', ['code' => '111111'])->assertSessionHasErrors('code');
        $this->post('/calacoto/verificar', ['code' => $code])->assertRedirect('/calacoto/mi-cuenta');
        $this->assertNotNull($c->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($c->fresh(), 'customer');
        $this->get('/calacoto/mi-cuenta')->assertOk()->assertSee('Ana Pérez');
    }

    public function test_no_entra_sin_verificar_y_recupera_contrasena(): void
    {
        $c = Customer::create(['name' => 'Luis', 'phone' => '70022444', 'email' => 'luis.q@gmail.com', 'password' => 'secreto1']);

        $this->post('/calacoto/ingresar', ['login' => 'luis.q@gmail.com', 'password' => 'secreto1'])->assertRedirect('/calacoto/verificar');
        $this->assertGuest('customer');

        $this->post('/calacoto/recuperar', ['email' => 'luis.q@gmail.com'])->assertRedirect('/calacoto/recuperar/codigo');
        $code = $this->ultimoCodigo('luis.q@gmail.com');
        $this->post('/calacoto/recuperar/codigo', ['code' => $code, 'password' => 'nueva123', 'password_confirmation' => 'nueva123'])->assertRedirect('/calacoto/mi-cuenta');
        $this->assertNotNull($c->fresh()->email_verified_at, 'Recibir el código también verifica el Gmail');

        $this->post('/calacoto/salir');
        $this->post('/calacoto/ingresar', ['login' => '70022444', 'password' => 'nueva123'])->assertRedirect('/calacoto/mi-cuenta');
    }

    public function test_cliente_creado_en_tienda_activa_su_cuenta(): void
    {
        $c = Customer::create(['name' => 'Cliente Tienda', 'phone' => '70055566', 'email' => 'cliente.tienda@gmail.com']);
        $this->post('/comercio/recuperar', ['email' => 'cliente.tienda@gmail.com']);
        $code = $this->ultimoCodigo('cliente.tienda@gmail.com');
        $this->post('/comercio/recuperar/codigo', ['code' => $code, 'password' => 'clave123', 'password_confirmation' => 'clave123'])->assertRedirect('/comercio/mi-cuenta');
        $this->assertNotNull($c->fresh()->password);
    }

    public function test_mi_cuenta_cambiar_datos_y_gmail(): void
    {
        $c = Customer::create(['name' => 'Rosa', 'phone' => '70033555', 'email' => 'rosa@gmail.com', 'password' => 'secreto1', 'email_verified_at' => now()]);
        $this->actingAs($c, 'customer');

        $this->put('/calacoto/mi-cuenta', ['name' => 'Rosa Q.', 'phone' => '70033555', 'email' => 'rosa@gmail.com', 'city' => 'El Alto'])->assertSessionHasNoErrors();
        $this->assertSame('Rosa Q.', $c->fresh()->name);

        $this->put('/calacoto/mi-cuenta', ['name' => 'Rosa Q.', 'phone' => '70033555', 'email' => 'rosa.nueva@gmail.com'])->assertRedirect('/calacoto/verificar');
        $this->assertNull($c->fresh()->email_verified_at);

        $this->put('/calacoto/mi-cuenta/clave', ['current_password' => 'mala', 'password' => 'nueva123', 'password_confirmation' => 'nueva123']);
    }

    public function test_me_gusta_comentarios_y_whatsapp(): void
    {
        $p = $this->producto();
        $post = Post::create(['user_id' => $this->admin()->id, 'branch_id' => $this->branch('calacoto')->id, 'type' => 'texto', 'title' => 'Comentable', 'body' => 'x', 'published_at' => now()]);

        $this->postJson("/calacoto/me-gusta/product/{$p->id}")->assertOk()->assertJson(['liked' => true, 'count' => 1]);

        $this->post("/calacoto/comentar/post/{$post->id}", ['body' => 'hola'])->assertRedirect('/calacoto/ingresar');

        $c = Customer::create(['name' => 'Marco', 'phone' => '70044666', 'email' => 'marco@gmail.com', 'password' => 'secreto1', 'email_verified_at' => now()]);
        $this->actingAs($c, 'customer')->post("/calacoto/comentar/post/{$post->id}", ['body' => '¿Precio?'])->assertRedirect();
        $this->assertTrue($post->comments()->where('customer_id', $c->id)->exists());
        $this->get('/calacoto/mi-cuenta')->assertOk();

        $this->get("/calacoto/whatsapp?producto={$p->slug}&origen=producto")->assertRedirectContains('wa.me/');
        $this->assertTrue($c->whatsappLeads()->where('product_id', $p->id)->exists());
    }
}
