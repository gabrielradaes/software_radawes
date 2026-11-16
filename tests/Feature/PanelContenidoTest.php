<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Product;
use App\Models\WhatsappLead;
use Tests\TestCase;

/** Panel: publicaciones (fotos, video, carrusel/separadas), comentarios y leads de WhatsApp. */
class PanelContenidoTest extends TestCase
{
    private function producto(): Product
    {
        $p = Product::create([
            'category_id' => \App\Models\Category::where('slug', 'botines')->value('id'),
            'name' => 'Botín Chelsea', 'slug' => 'botin-chelsea', 'base_price' => 490,
            'is_active' => true, 'published_at' => now()->subDay(),
        ]);
        $p->branches()->attach($this->branch('calacoto')->id, ['is_active' => true]);
        $p->images()->create(['path' => 'uploads/products/ejemplo.jpg', 'alt' => 'x', 'is_primary' => true]);

        return $p;
    }

    public function test_publicacion_con_fotos_en_carrusel(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/publicaciones/create')->assertOk();

        $this->actingAs($admin)->post('/admin/publicaciones', [
            'title' => 'Llegaron los Chelsea', 'body' => 'Nueva colección **en cuero**',
            'type' => 'foto', 'branch_id' => $this->branch('calacoto')->id, 'published' => 1, 'layout' => 'carousel',
            'media' => [$this->foto('a.jpg'), $this->foto('b.png')],
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/publicaciones');

        $post = Post::where('title', 'Llegaron los Chelsea')->firstOrFail();
        $this->assertCount(2, $post->media);
        $post->media->each(fn ($m) => $this->assertFotoGuardada($m->path));

        $this->get('/calacoto/novedades')->assertOk()->assertSee('Llegaron los Chelsea');
        $this->get("/calacoto/novedades/{$post->id}")->assertOk();
        $this->get('/calacoto')->assertOk()->assertSee('Llegaron los Chelsea');
        $this->get('/')->assertOk();
    }

    public function test_publicacion_separadas_con_video_y_fotos_del_producto(): void
    {
        $admin = $this->admin();
        $product = $this->producto();

        $this->actingAs($admin)->post('/admin/publicaciones', [
            'title' => 'Mira el video', 'type' => 'foto', 'branch_id' => $this->branch('calacoto')->id,
            'product_id' => $product->id, 'use_product_images' => 1, 'published' => 1, 'layout' => 'split',
            'video_url' => 'https://www.facebook.com/reel/976914087611374',
        ])->assertSessionHasNoErrors();

        $posts = Post::where('title', 'Mira el video')->with('media')->get();
        $this->assertCount(2, $posts, 'Separadas: una publicación para el video y otra para la foto');
        $this->assertSame(['video', 'imagen'], $posts->map(fn ($p) => $p->media->first()->type)->sort()->values()->reverse()->values()->all());
        $this->get('/calacoto/novedades')->assertOk()->assertSee('facebook.com/plugins/video.php', false);
    }

    public function test_publicacion_solo_texto_programada_y_borrador(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/publicaciones', ['title' => 'Horario', 'body' => 'Abrimos 9:00', 'type' => 'texto', 'published' => 1, 'layout' => 'carousel'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/publicaciones', ['title' => 'Borrador', 'body' => 'x', 'type' => 'texto', 'layout' => 'carousel'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/publicaciones', ['title' => 'Programada', 'body' => 'x', 'type' => 'texto', 'published' => 1, 'published_at' => now()->addDay()->format('Y-m-d\TH:i'), 'layout' => 'carousel'])
            ->assertSessionHasNoErrors();

        $this->get('/comercio/novedades')->assertSee('Horario')->assertDontSee('Borrador')->assertDontSee('Programada');

        $this->actingAs($admin)->post('/admin/publicaciones', ['type' => 'texto', 'layout' => 'carousel'])->assertSessionHasErrors('body');
        $this->actingAs($admin)->post('/admin/publicaciones', ['title' => 'x', 'type' => 'foto', 'media' => [$this->fotoFalsa()], 'layout' => 'carousel'])->assertSessionHasErrors('media.0');
        $this->actingAs($admin)->post('/admin/publicaciones', ['title' => 'x', 'type' => 'foto', 'video_url' => 'https://vimeo.com/123', 'layout' => 'carousel'])->assertSessionHasErrors('video_url');
    }

    public function test_editar_quitar_foto_y_borrar_publicacion(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/publicaciones', ['title' => 'Editable', 'type' => 'foto', 'published' => 1, 'layout' => 'carousel', 'media' => [$this->foto()]]);
        $post = Post::where('title', 'Editable')->firstOrFail();

        $this->actingAs($admin)->get("/admin/publicaciones/{$post->id}/edit")->assertOk();
        $this->actingAs($admin)->put("/admin/publicaciones/{$post->id}", ['title' => 'Editada', 'type' => 'foto', 'published' => 1, 'is_pinned' => 1, 'media' => [$this->foto('otra.jpg')]])
            ->assertSessionHasNoErrors();
        $post->refresh();
        $this->assertSame('Editada', $post->title);
        $this->assertTrue($post->is_pinned);
        $this->assertCount(2, $post->media);

        $media = $post->media->first();
        $this->actingAs($admin)->delete("/admin/media/{$media->id}")->assertRedirect();
        $this->assertFileDoesNotExist(public_path($media->path));

        $this->actingAs($admin)->delete("/admin/publicaciones/{$post->id}")->assertRedirect('/admin/publicaciones');
        $this->assertSoftDeleted($post);
    }

    public function test_supervisor_publica_solo_en_su_tienda(): void
    {
        $sup = $this->supervisor('calacoto');
        $this->actingAs($sup)->post('/admin/publicaciones', [
            'title' => 'Del supervisor', 'type' => 'foto', 'branch_id' => $this->branch('el-alto')->id,
            'published' => 1, 'layout' => 'carousel', 'media' => [$this->foto()],
        ])->assertSessionHasNoErrors();
        $this->assertSame('calacoto', Post::where('title', 'Del supervisor')->first()->branch->slug);

        $ajena = Post::create(['user_id' => $this->admin()->id, 'branch_id' => $this->branch('comercio')->id, 'type' => 'texto', 'title' => 'Ajena', 'body' => 'x', 'published_at' => now()]);
        $this->actingAs($sup)->get("/admin/publicaciones/{$ajena->id}/edit")->assertForbidden();
        $this->actingAs($sup)->get('/admin/publicaciones')->assertOk()->assertDontSee('Ajena');
    }

    public function test_moderar_comentarios_y_leads(): void
    {
        $admin = $this->admin();
        $post = Post::create(['user_id' => $admin->id, 'branch_id' => $this->branch('calacoto')->id, 'type' => 'texto', 'title' => 'Con comentarios', 'body' => 'x', 'published_at' => now()]);
        $c = $post->comments()->create(['author_name' => 'Ana', 'body' => '¿Tienen 42?', 'is_approved' => true]);

        $this->actingAs($admin)->get('/admin/comentarios')->assertOk()->assertSee('¿Tienen 42?');
        $this->actingAs($admin)->post("/admin/comentarios/{$c->id}/responder", ['body' => 'Sí, en café'])->assertRedirect();
        $this->assertTrue(Comment::where('parent_id', $c->id)->whereNotNull('user_id')->exists());
        $this->actingAs($admin)->patch("/admin/comentarios/{$c->id}/aprobar")->assertRedirect();
        $this->assertFalse($c->fresh()->is_approved);
        $this->actingAs($admin)->delete("/admin/comentarios/{$c->id}")->assertRedirect();
        $this->assertNull(Comment::find($c->id));

        $this->get('/calacoto/whatsapp?origen=prueba')->assertRedirectContains('wa.me/');
        $lead = WhatsappLead::latest('id')->firstOrFail();
        $this->actingAs($admin)->get('/admin/leads')->assertOk();
        $this->actingAs($admin)->patch("/admin/leads/{$lead->id}", ['status' => 'vendido'])->assertRedirect();
        $this->assertSame('vendido', $lead->fresh()->status);
    }
}
