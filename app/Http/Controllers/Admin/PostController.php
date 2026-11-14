<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        return view('admin.posts.form', $this->formData(new Post(['type' => 'foto', 'branch_id' => $request->user()->branch_id]), $request));
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

        return redirect()->route('admin.publicaciones.index')
            ->with('status', $created > 1 ? "Se crearon $created publicaciones separadas." : 'Publicación creada.')
            ->with('status_link', $link ? ['url' => $link, 'label' => 'Ver en la tienda ↗'] : null);
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

        return back()->with('status', 'Publicación actualizada.');
    }

    public function destroy(Request $request, Post $post)
    {
        $this->authorizeBranch($request, $post);
        $post->delete();

        return redirect()->route('admin.publicaciones.index')->with('status', 'Publicación eliminada.');
    }

    public function destroyMedia(Request $request, PostMedia $media)
    {
        $this->authorizeBranch($request, $media->post);
        if (str_starts_with($media->path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($media->path, 8));
        }
        $media->delete();

        return back()->with('status', 'Imagen eliminada.');
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
            'media.*' => ['image', 'max:6144'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'use_product_images' => ['boolean'],
        ], [], ['title' => 'título', 'body' => 'texto', 'video_url' => 'enlace de video']);

        if (! empty($data['video_url']) && ! video_embed_url($data['video_url'])) {
            abort(back()->withErrors(['video_url' => 'Solo se aceptan enlaces de Facebook, TikTok o YouTube.'])->withInput());
        }
        if (! $data['title'] && ! $data['body'] && ! $request->hasFile('media') && empty($data['video_url'])) {
            abort(back()->withErrors(['body' => 'La publicación necesita texto o imágenes.'])->withInput());
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
        unset($data['published'], $data['media'], $data['use_product_images'], $data['video_url']);

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

        if ($useProductImages) {
            foreach (Product::find($post->product_id)?->images ?? [] as $img) {
                $post->media()->create(['type' => 'imagen', 'path' => $img->path, 'sort_order' => ++$order]);
            }
        }

        foreach ($request->file('media', []) as $file) {
            $path = $file->store('posts', 'uploads');
            $post->media()->create(['type' => 'imagen', 'path' => 'uploads/'.$path, 'sort_order' => ++$order]);
        }
    }
}
