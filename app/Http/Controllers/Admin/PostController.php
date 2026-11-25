<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Product;
use App\Support\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Publicaciones del feed. El admin ve todas; el supervisor solo las de su tienda.
 */
class PostController extends Controller
{
    public function index(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();

        $posts = Post::with(['author', 'branch', 'product', 'media'])
            ->withCount(['likes', 'comments'])
            ->when($scoped, fn ($q) => $q->where('branch_id', $scoped))
            ->when(! $scoped && $request->filled('sucursal'), fn ($q) => $q->where('branch_id', $request->sucursal))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->q.'%'))
            ->orderByDesc('is_pinned')->latest('published_at')->latest()
            ->paginate(15)->withQueryString();

        $branches = $scoped ? collect() : Branch::active()->get();

        return view('admin.posts.index', compact('posts', 'branches'));
    }

    public function create(Request $request)
    {
        return view('admin.posts.form', $this->formData(new Post(['type' => 'foto', 'branch_id' => $request->user()->branch_id, 'product_id' => $request->integer('producto') ?: null]), $request));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;

        $post = Post::create($data);
        $this->storeMedia($request, $post);

        // "Separadas": una publicación por cada foto/video (la primera se queda con el medio 1)
        $created = 1;
        if ($request->input('layout') === 'split' && $post->media()->count() > 1) {
            $extra = $post->media()->orderBy('sort_order')->get()->slice(1);
            foreach ($extra as $i => $m) {
                $copy = Post::create([...$data, 'is_pinned' => false, 'published_at' => $data['published_at']?->copy()->addSeconds($i + 1) ?? null]);
                $m->update(['post_id' => $copy->id, 'sort_order' => 1]);
                $created++;
            }
        }

        $store = $post->branch ?? \App\Models\Branch::active()->where('is_main', true)->first();
        $link = $post->published_at && $post->published_at->isPast() && $store ? route('feed.show', ['branch' => $store, 'post' => $post]) : null;

        return upload_aware(redirect()->route('admin.publicaciones.index')
            ->with('status', $created > 1 ? "Se crearon $created publicaciones separadas." : 'Publicación creada.')
            ->with('status_link', $link ? ['url' => $link, 'label' => 'Ver en la tienda ↗'] : null));
    }

    public function edit(Request $request, Post $post)
    {
        $this->authorizeBranch($request, $post);
        $post->load('media');

        return view('admin.posts.form', $this->formData($post, $request));
    }

    public function update(Request $request, Post $post)
    {
        $this->authorizeBranch($request, $post);
        $post->update($this->validated($request, $post));
        $this->storeMedia($request, $post);

        return upload_aware(redirect()->route('admin.publicaciones.edit', $post)->with('status', 'Publicación actualizada.'));
    }

    public function destroy(Request $request, Post $post)
    {
        ensure_can_delete();
        $this->authorizeBranch($request, $post);
        $post->delete();

        return redirect()->route('admin.publicaciones.index')->with('status', 'Publicación eliminada.');
    }

    public function destroyMedia(Request $request, PostMedia $media)
    {
        $this->authorizeBranch($request, $media->post);
        // No borrar el archivo si otra publicación o un producto usan la misma foto
        $shared = PostMedia::where('path', $media->path)->whereKeyNot($media->id)->exists()
            || \App\Models\ProductImage::where('path', $media->path)->exists();
        if (! $shared) {
            ImageUpload::delete($media->path);       // foto o video subido (los enlaces no se tocan)
            ImageUpload::delete($media->thumbnail_path);
        }
        $media->delete();

        return back()->with('status', $media->type === 'video' ? 'Video quitado.' : 'Imagen quitada.');
    }

    /** El supervisor solo puede tocar publicaciones de su propia tienda. */
    protected function authorizeBranch(Request $request, Post $post): void
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && $post->branch_id !== $scoped, 403, 'Esta publicación pertenece a otra tienda.');
    }

    protected function formData(Post $post, Request $request): array
    {
        $scoped = $request->user()->scopedBranchId();

        return [
            'post' => $post,
            'branches' => Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->get(),
            'scoped' => $scoped,
            'products' => Product::orderBy('name')
                ->when($scoped, fn ($q) => $q->inBranch($scoped))
                ->get(['id', 'name']),
        ];
    }

    protected function validated(Request $request, ?Post $post = null): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'in:foto,video,texto,oferta'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'is_pinned' => ['boolean'],
            'published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'media.*' => [ImageUpload::rule(6144)],
            'videos' => ['nullable', 'array', 'max:5'],
            'videos.*' => [\App\Support\VideoUpload::rule()],
            'video_posters' => ['nullable', 'array'],
            'video_posters.*' => ['nullable', ImageUpload::rule(3072)],
            'video_url' => ['nullable', 'url', 'max:500'],
            'use_product_images' => ['boolean'],
            'bg' => ['nullable', 'string', 'max:40', fn ($a, $v, $fail) => Post::validBg($v) ? null : $fail('Elige un color de la lista o uno propio.')],
            'text_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [], ['title' => 'título', 'body' => 'texto', 'video_url' => 'enlace de video', 'videos.*' => 'video', 'text_color' => 'color de letra']);
        $data['bg'] = ($data['bg'] ?? null) ?: null;
        $data['text_color'] = ($data['text_color'] ?? null) ?: null;

        if (! empty($data['video_url']) && ! video_embed_url($data['video_url'])) {
            throw ValidationException::withMessages(['video_url' => 'Solo se aceptan enlaces de Facebook, TikTok o YouTube.']);
        }
        if (empty($data['title']) && empty($data['body']) && ! $request->hasFile('media') && ! $request->hasFile('videos') && empty($data['video_url'])
            && ! ($request->boolean('use_product_images') && ! empty($data['product_id']))) {
            throw ValidationException::withMessages(['body' => 'La publicación necesita texto, fotos o un video.']);
        }

        // El supervisor publica siempre en su tienda
        if ($scoped = $request->user()->scopedBranchId()) {
            $data['branch_id'] = $scoped;
        }

        $data['is_pinned'] = $request->boolean('is_pinned');
        if ($request->boolean('published')) {
            $data['published_at'] = $data['published_at'] ?? $post?->published_at ?? now();
        } else {
            $data['published_at'] = null;
        }
        if ($request->hasFile('videos')) {
            $data['type'] = 'video';
        }
        unset($data['published'], $data['media'], $data['use_product_images'], $data['video_url'], $data['videos'], $data['video_posters']);

        return $data;
    }

    protected function storeMedia(Request $request, Post $post): void
    {
        $order = (int) $post->media()->max('sort_order');
        // Decidir ANTES de agregar el video: si no hay fotos propias, usar las del producto
        $useProductImages = $request->boolean('use_product_images') && $post->product_id && ! $post->media()->exists() && ! $request->hasFile('media');

        if ($request->filled('video_url')) {
            // El video va primero en el carrusel
            $post->media()->create(['type' => 'video', 'path' => trim($request->video_url), 'sort_order' => 0]);
            $order = max($order, 0);
            if ($post->type !== 'video') {
                $post->update(['type' => 'video']);
            }
        }

        // Videos subidos (del editor o del celular), cada uno con su portada si vino
        $posters = $request->file('video_posters', []);
        foreach ($request->file('videos', []) as $key => $file) {
            $path = \App\Support\VideoUpload::store($file);
            $poster = isset($posters[$key]) && $posters[$key] instanceof \Illuminate\Http\UploadedFile ? ImageUpload::store($posters[$key], 'videos') : null;
            $post->media()->create(['type' => 'video', 'path' => $path, 'thumbnail_path' => $poster, 'sort_order' => ++$order]);
        }

        if ($useProductImages) {
            foreach (Product::find($post->product_id)?->images ?? [] as $img) {
                $post->media()->create(['type' => 'imagen', 'path' => $img->path, 'sort_order' => ++$order]);
            }
        }

        foreach ($request->file('media', []) as $file) {
            $path = ImageUpload::store($file, 'posts');
            $post->media()->create(['type' => 'imagen', 'path' => $path, 'sort_order' => ++$order]);
        }
    }
}
