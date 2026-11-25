<?php

use App\Models\Branch;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

/**
 * Trae a las novedades de Comercio y Calacoto las fotos y videos que ya están en nuestras redes
 * (TikTok y Facebook), cada tienda con sus propios textos (ver database/data/redes-sociales.php).
 * Se muestran desde la red, no se copian archivos. Si una publicación ya existe en esa tienda, no se repite.
 */
return new class extends Migration
{
    public function up(): void
    {
        $author = User::whereHas('role', fn ($r) => $r->where('name', Role::ADMIN))->orderBy('id')->first();
        $branches = Branch::whereIn('slug', ['comercio', 'calacoto'])->get()->keyBy('slug');
        if (! $author || $branches->isEmpty()) {
            return;   // instalación nueva o pruebas: no hay a quién ni dónde publicar
        }

        $tz = config('app.timezone');
        foreach (require database_path('data/redes-sociales.php') as $item) {
            foreach ($branches as $slug => $branch) {
                [$title, $body, $bg] = $item[$slug];
                $links = $slug === 'calacoto' ? array_reverse($item['links']) : $item['links'];

                $exists = PostMedia::whereIn('path', $item['links'])
                    ->whereHas('post', fn ($q) => $q->where('branch_id', $branch->id))->exists();
                if ($exists) {
                    continue;
                }

                $at = Carbon::parse($item['at'], 'UTC')->setTimezone($tz);
                if ($slug === 'calacoto') {
                    $at = $at->addDay()->addHours(3)->addMinutes(17);   // otra fecha y hora: no es una copia
                }
                if ($at->isFuture()) {
                    $at = now()->subMinutes(5);
                }

                $post = Post::create([
                    'user_id' => $author->id, 'branch_id' => $branch->id, 'type' => $item['type'],
                    'title' => $title, 'body' => $body, 'bg' => $bg, 'published_at' => $at,
                ]);
                foreach ($links as $i => $url) {
                    $post->media()->create(['type' => 'video', 'path' => $url, 'sort_order' => $i + 1]);
                }
            }
        }
    }

    public function down(): void
    {
        $links = collect(require database_path('data/redes-sociales.php'))->pluck('links')->flatten();
        $ids = PostMedia::whereIn('path', $links)->pluck('post_id')->unique();
        PostMedia::whereIn('post_id', $ids)->delete();
        Post::whereIn('id', $ids)->forceDelete();
    }
};
