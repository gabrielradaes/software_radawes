<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Cargos (supervisor casi todo menos eliminar; vendedor publica), historial de actividad, colores y videos en publicaciones, ayuda. */
class EquipoActividadTest extends TestCase
{
    private function producto(string $branch = 'calacoto'): Product
    {
        $p = Product::create([
            'category_id' => Category::where('slug', 'botines')->value('id'),
            'name' => 'Botín Chelsea', 'slug' => 'botin-chelsea', 'code' => 'CH-210', 'base_price' => 490,
            'is_active' => true, 'published_at' => now()->subDay(),
        ]);
        $p->branches()->attach($this->branch($branch)->id, ['is_active' => true]);

        return $p;
    }

    /** Un MP4 mínimo: lo que importa es la firma "ftyp" que se revisa al subir. */
    private function video(string $name = 'clip.mp4', string $kind = 'mp4'): UploadedFile
    {
        $head = $kind === 'webm' ? "\x1A\x45\xDF\xA3".str_repeat("\0", 60) : "\0\0\0\x18ftypmp42\0\0\0\0mp42isom".str_repeat("\0", 40);

        return UploadedFile::fake()->createWithContent($name, $head);
    }

    // ------------------------------------------------------------ cargos

    public function test_solo_el_admin_elimina_y_el_supervisor_hace_lo_demas(): void
    {
        $p = $this->producto();
        $sup = $this->supervisor('calacoto');
        $post = Post::create(['user_id' => $sup->id, 'branch_id' => $this->branch('calacoto')->id, 'type' => 'texto', 'title' => 'Hola', 'published_at' => now()]);

        // El supervisor edita, pero no elimina (y no ve los botones de eliminar)
        $this->actingAs($sup)->get('/admin/productos')->assertOk()->assertDontSee('¿Eliminar Botín Chelsea?');
        $this->actingAs($sup)->delete("/admin/productos/{$p->id}")->assertForbidden();
        $this->actingAs($sup)->delete("/admin/publicaciones/{$post->id}")->assertForbidden();
        $this->actingAs($sup)->put("/admin/publicaciones/{$post->id}", ['title' => 'Hola editado', 'type' => 'texto', 'published' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Hola editado', $post->fresh()->title);
        $this->assertNotSoftDeleted($p);

        // Clientes: el supervisor ahora también los edita
        $c = Customer::create(['name' => 'Rosa', 'phone' => '70011222', 'is_active' => true]);
        $this->actingAs($sup)->patch("/admin/clientes/{$c->id}", ['notes' => 'Talla 38'])->assertRedirect();

        // El admin sí elimina
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/productos')->assertSee('¿Eliminar Botín Chelsea?');
        $this->actingAs($admin)->delete("/admin/publicaciones/{$post->id}")->assertRedirect();
        $this->assertSoftDeleted($post);
    }

    public function test_el_vendedor_publica_en_su_tienda_pero_no_elimina(): void
    {
        $vend = $this->vendedor('el-alto');
        $this->actingAs($vend)->get('/admin')->assertOk()->assertSee('Publicar')->assertSee('Vender')->assertDontSee('Llegó mercadería');
        $this->actingAs($vend)->get('/admin/publicaciones/create')->assertOk()->assertSee('Crear video con el editor');
        $this->actingAs($vend)->post('/admin/publicaciones', ['title' => 'Llegaron sandalias', 'type' => 'foto', 'published' => 1, 'layout' => 'carousel', 'branch_id' => $this->branch('calacoto')->id, 'media' => [$this->foto()]])
            ->assertSessionHasNoErrors();
        $post = Post::where('title', 'Llegaron sandalias')->firstOrFail();
        $this->assertSame($this->branch('el-alto')->id, $post->branch_id, 'Siempre publica en su propia tienda');
        $this->actingAs($vend)->delete("/admin/publicaciones/{$post->id}")->assertForbidden();
        $this->actingAs($vend)->get('/admin/compras')->assertForbidden();
        $this->actingAs($vend)->get('/admin/actividad')->assertForbidden();
        $this->actingAs($vend)->get('/admin/mi-actividad')->assertOk()->assertSee('Publicó en la página');
    }

    // ------------------------------------------------------------ historial

    public function test_el_historial_anota_quien_hizo_que_y_donde(): void
    {
        $vend = $this->vendedor('calacoto');
        $this->actingAs($vend)->post('/admin/publicaciones', ['title' => 'Oferta de botas', 'type' => 'oferta', 'published' => 1, 'layout' => 'carousel'])->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'accion')->latest('id')->firstOrFail();
        $this->assertSame($vend->id, $log->user_id);
        $this->assertSame($this->branch('calacoto')->id, $log->branch_id);
        $this->assertStringContainsString('Publicó en la página', $log->description);
        $this->assertStringContainsString('Publicación creada', $log->description);

        // Si falta algo (error de validación) no se anota como hecho
        $before = AuditLog::count();
        $this->actingAs($vend)->post('/admin/publicaciones', ['type' => 'texto', 'layout' => 'carousel'])->assertSessionHasErrors('body');
        $this->assertSame($before, AuditLog::count());

        // Mirar no se anota
        $this->actingAs($vend)->get('/admin/publicaciones');
        $this->assertSame($before, AuditLog::count());

        // Supervisor de la tienda lo ve; el de otra tienda no; el admin ve todo
        $this->actingAs($this->supervisor('calacoto'))->get('/admin/actividad')->assertOk()->assertSee('Publicó en la página')->assertSee($vend->name);
        $this->actingAs($this->supervisor('el-alto'))->get('/admin/actividad')->assertOk()->assertDontSee('Oferta de botas');
        $this->actingAs($this->admin())->get('/admin/actividad?usuario='.$vend->id)->assertOk()->assertSee('Publicó en la página');
        $this->actingAs($this->admin())->get('/admin/auditoria')->assertRedirect('/admin/actividad');
    }

    public function test_inicios_de_sesion_y_acciones_de_clientes(): void
    {
        $sup = $this->supervisor('calacoto');
        $this->post('/admin/login', ['email' => $sup->email, 'password' => 'mala-clave'])->assertSessionHasErrors();
        $this->assertTrue(AuditLog::where('action', 'ingreso_fallido')->where('description', 'like', '%'.$sup->email.'%')->exists());
        $this->assertFalse(AuditLog::where('description', 'like', '%mala-clave%')->exists(), 'Nunca se guarda la contraseña');

        $this->post('/admin/login', ['email' => $sup->email, 'password' => 'clave-segura-123'])->assertRedirect();
        $this->assertTrue(AuditLog::where('user_id', $sup->id)->where('description', 'like', 'Inició sesión%')->exists());
        $this->post('/admin/logout')->assertRedirect();
        $this->assertTrue(AuditLog::where('user_id', $sup->id)->where('description', 'Cerró sesión')->exists());

        // Cliente: comenta en una publicación de Calacoto → lo ve en "Mi actividad" y en su ficha del panel
        $post = Post::create(['user_id' => $sup->id, 'branch_id' => $this->branch('calacoto')->id, 'type' => 'texto', 'title' => 'Novedad', 'published_at' => now()]);
        $c = Customer::create(['name' => 'Rosa Quispe', 'phone' => '70055666', 'email' => 'rosa@gmail.com', 'password' => 'clave-segura-123', 'email_verified_at' => now(), 'is_active' => true]);
        $this->actingAs($c, 'customer')->post("/calacoto/comentar/post/{$post->id}", ['body' => '¿Tienen talla 38?'])->assertRedirect();
        $log = AuditLog::where('customer_id', $c->id)->latest('id')->firstOrFail();
        $this->assertNull($log->user_id);
        $this->assertSame($this->branch('calacoto')->id, $log->branch_id);
        $this->assertStringContainsString('¿Tienen talla 38?', $log->description);
        $this->actingAs($c, 'customer')->get('/calacoto/mi-cuenta')->assertOk()->assertSee('Mi actividad')->assertSee('Comentó');
        $this->actingAs($this->admin())->get("/admin/clientes/{$c->id}")->assertOk();
        $this->actingAs($this->admin())->get('/admin/actividad?quien=clientes')->assertOk()->assertSee('Rosa Quispe (cliente)');
    }

    public function test_los_cambios_de_datos_se_ven_debajo_de_la_accion(): void
    {
        $p = $this->producto();
        $admin = $this->admin();
        $this->actingAs($admin)->put("/admin/productos/{$p->id}", [
            'name' => $p->name, 'category_id' => $p->category_id, 'base_price' => 520, 'published' => 1, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $batch = AuditLog::where('action', 'accion')->latest('id')->value('batch');
        $this->assertNotNull($batch);
        $this->assertTrue(AuditLog::where('batch', $batch)->where('action', 'actualizado')->exists(), 'El cambio de precio queda junto a "Editó un producto"');
        $this->actingAs($admin)->get('/admin/actividad')->assertOk()->assertSee('Editó un producto')->assertSee('520.00');
    }

    // ------------------------------------------------------------ colores y videos

    public function test_publicacion_de_color_se_ve_con_ese_color(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/publicaciones', ['title' => 'Horario de Navidad', 'body' => 'Abrimos hasta las 22:00', 'type' => 'texto', 'published' => 1, 'layout' => 'carousel', 'bg' => 'g-atardecer'])
            ->assertSessionHasNoErrors();
        $post = Post::where('title', 'Horario de Navidad')->firstOrFail();
        $this->assertSame('g-atardecer', $post->bg);
        $this->get("/calacoto/novedades/{$post->id}")->assertOk()->assertSee('linear-gradient(135deg, #f97316, #db2777)', false)->assertSee('post-colored');

        // Color propio + letra elegida
        $this->actingAs($admin)->put("/admin/publicaciones/{$post->id}", ['title' => 'Horario de Navidad', 'type' => 'texto', 'published' => 1, 'bg' => '#123456', 'text_color' => '#ffcc00'])->assertSessionHasNoErrors();
        $this->get("/calacoto/novedades/{$post->id}")->assertSee('background: #123456; color: #ffcc00;', false);

        // Letra automática: fondo claro → letra oscura
        $post->refresh()->update(['bg' => '#f5f5f4', 'text_color' => null]);
        $this->assertSame('#111827', $post->fresh()->colorStyle()['fg']);

        // Nada de CSS inventado
        $this->actingAs($admin)->put("/admin/publicaciones/{$post->id}", ['title' => 'x', 'type' => 'texto', 'bg' => 'red;background:url(x)'])->assertSessionHasErrors('bg');
        $this->actingAs($admin)->put("/admin/publicaciones/{$post->id}", ['title' => 'x', 'type' => 'texto', 'text_color' => 'blue'])->assertSessionHasErrors('text_color');
    }

    public function test_subir_video_con_portada_y_rechazar_archivos_que_no_son_video(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/publicaciones', [
            'title' => 'Mira el nuevo Chelsea', 'type' => 'foto', 'published' => 1, 'layout' => 'carousel',
            'videos' => [$this->video()], 'video_posters' => [$this->foto('portada.jpg')], 'media' => [$this->foto()],
        ])->assertSessionHasNoErrors();
        $post = Post::where('title', 'Mira el nuevo Chelsea')->with('media')->firstOrFail();
        $this->assertSame('video', $post->type);
        $video = $post->media->first();
        $this->assertTrue($video->isLocalVideo(), 'El video va primero en el carrusel');
        $this->assertStringEndsWith('.mp4', $video->path);
        $this->assertFileExists(public_path($video->path));
        $this->assertNotNull($video->thumbnail_path);
        $this->get("/calacoto/novedades/{$post->id}")->assertOk()->assertSee('<video', false)->assertSee($video->thumbnail_path);

        // WebM del editor también vale
        $this->actingAs($admin)->post('/admin/publicaciones', ['title' => 'Reel', 'type' => 'video', 'published' => 1, 'layout' => 'carousel', 'videos' => [$this->video('reel.webm', 'webm')]])->assertSessionHasNoErrors();
        $this->assertStringEndsWith('.webm', Post::where('title', 'Reel')->firstOrFail()->media->first()->path);

        // Un archivo que no es video se rechaza con un mensaje claro
        $this->actingAs($admin)->post('/admin/publicaciones', ['title' => 'Falso', 'type' => 'video', 'layout' => 'carousel', 'videos' => [UploadedFile::fake()->createWithContent('virus.mp4', 'MZ esto no es un video')]])
            ->assertSessionHasErrors('videos.0');

        // Quitar el video borra el archivo y la portada
        $this->actingAs($admin)->delete("/admin/media/{$video->id}")->assertRedirect();
        $this->assertFileDoesNotExist(public_path($video->path));
        $this->assertFileDoesNotExist(public_path($video->thumbnail_path));
    }

    public function test_subida_con_barra_de_progreso_responde_json(): void
    {
        $admin = $this->admin();
        // Éxito: el navegador recibe a dónde ir
        $this->actingAs($admin)->withHeaders(['X-RyE-Upload' => '1', 'Accept' => 'application/json'])
            ->post('/admin/publicaciones', ['title' => 'Con progreso', 'type' => 'foto', 'published' => 1, 'layout' => 'carousel', 'media' => [$this->foto()]])
            ->assertOk()->assertJson(['redirect' => url('/admin/publicaciones')]);
        // Error: los mensajes llegan en JSON (las fotos siguen en el formulario)
        $this->actingAs($admin)->withHeaders(['X-RyE-Upload' => '1', 'Accept' => 'application/json'])
            ->post('/admin/publicaciones', ['type' => 'texto', 'layout' => 'carousel'])
            ->assertStatus(422)->assertJsonValidationErrors('body');
    }

    // ------------------------------------------------------------ ayuda

    public function test_cada_pantalla_principal_tiene_su_ayuda(): void
    {
        $admin = $this->admin();
        foreach (['/admin', '/admin/ventas/nueva', '/admin/publicaciones/create', '/admin/stock', '/admin/caja', '/admin/actividad', '/admin/mi-actividad'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('¿Cómo se usa?');
        }
    }
}
