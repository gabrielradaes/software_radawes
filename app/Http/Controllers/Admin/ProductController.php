<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();

        $products = Product::with(['category', 'brand', 'primaryImage', 'branches'])
            ->withCount(['variants', 'likes', 'whatsappLeads'])
            ->when($scoped, fn ($q) => $q->inBranch($scoped))
            ->when(! $scoped && $request->filled('sucursal'), fn ($q) => $q->inBranch((int) $request->sucursal))
            ->search($request->query('q'))
            ->when($request->filled('categoria'), fn ($q) => $q->where('category_id', $request->categoria))
            ->when($request->estado === 'inactivos', fn ($q) => $q->where('is_active', false))
            ->when($request->estado === 'borradores', fn ($q) => $q->whereNull('published_at'))
            ->latest()
            ->paginate(20)->withQueryString();

        // Stock total por producto (todas las sucursales)
        $stockTotals = \App\Models\Stock::join('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->whereIn('product_variants.product_id', $products->pluck('id'))
            ->groupBy('product_variants.product_id')
            ->selectRaw('product_variants.product_id, sum(stocks.quantity) as total')
            ->pluck('total', 'product_id');

        $categories = Category::orderBy('parent_id')->orderBy('sort_order')->get();
        $branches = $scoped ? collect() : \App\Models\Branch::active()->get();

        return view('admin.products.index', compact('products', 'categories', 'stockTotals', 'branches'));
    }

    public function create(Request $request)
    {
        return view('admin.products.form', $this->formData(new Product(['is_active' => true, 'currency' => 'BOB', 'origin_country' => 'Brasil']), $request));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        $product = Product::create($data);
        $this->syncBranches($request, $product);
        $this->storeImages($request, $product);
        $this->storeSpecs($request, $product);

        return redirect()->route('admin.productos.variantes', $product)
            ->with('status', 'Producto creado. Ahora genera sus variantes y stock.');
    }

    public function edit(Request $request, Product $product)
    {
        $this->authorizeBranch($request, $product);
        $product->load(['images', 'specs', 'branches']);

        return view('admin.products.form', $this->formData($product, $request));
    }

    /** El supervisor solo edita productos que se venden en su tienda. */
    protected function authorizeBranch(Request $request, Product $product): void
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && ! $product->branches()->where('branches.id', $scoped)->exists(), 403, 'Este producto no pertenece a tu tienda.');
    }

    /** Tiendas donde se vende: el admin elige; el supervisor siempre agrega la suya. */
    protected function syncBranches(Request $request, Product $product): void
    {
        $scoped = $request->user()->scopedBranchId();
        if ($scoped) {
            $product->branches()->syncWithoutDetaching([$scoped => ['is_active' => true]]);

            return;
        }
        $ids = collect($request->input('branches', []))->map(fn ($id) => (int) $id)->filter();
        if ($ids->isEmpty()) {
            $ids = collect([\App\Models\Branch::active()->where('is_main', true)->value('id')])->filter();
        }
        $product->branches()->sync($ids->mapWithKeys(fn ($id) => [$id => ['is_active' => true]])->all());
    }

    protected function formData(Product $product, Request $request): array
    {
        $scoped = $request->user()->scopedBranchId();
        $roots = Category::roots()->with(['children', 'specFields'])->get();
        $categoryRoots = [];
        foreach ($roots as $r) {
            $categoryRoots[$r->id] = $r->id;
            foreach ($r->children as $c) {
                $categoryRoots[$c->id] = $r->id;
            }
        }

        return [
            'product' => $product,
            'categories' => $this->categoryOptions(),
            'brands' => Brand::orderBy('name')->get(),
            'specGroups' => $roots->filter(fn ($r) => $r->specFields->count()),
            'categoryRoots' => $categoryRoots,
            'branches' => \App\Models\Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->orderByDesc('is_main')->get(),
            'scoped' => $scoped,
            'selectedBranches' => $product->exists ? $product->branches->pluck('id')->all() : array_filter([$scoped ?? \App\Models\Branch::active()->where('is_main', true)->value('id')]),
        ];
    }

    /** Guarda la ficha técnica: solo campos de la categoría principal del producto; vacíos se eliminan. */
    protected function storeSpecs(Request $request, Product $product): void
    {
        $rootId = $product->category->root()->id;
        $validIds = \App\Models\SpecField::where('category_id', $rootId)->pluck('id');

        foreach ($request->input('spec', []) as $fieldId => $value) {
            if (! $validIds->contains((int) $fieldId)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value === '') {
                $product->specs()->where('spec_field_id', $fieldId)->delete();
            } else {
                $product->specs()->updateOrCreate(['spec_field_id' => $fieldId], ['value' => mb_substr($value, 0, 255)]);
            }
        }
        // Si cambió de categoría principal, limpiar campos que ya no aplican
        $product->specs()->whereNotIn('spec_field_id', $validIds)->delete();
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeBranch($request, $product);
        $data = $this->validated($request, $product);
        if ($data['name'] !== $product->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $product->id);
        }
        $product->update($data);
        $product->unsetRelation('category');
        $this->syncBranches($request, $product);
        $this->storeImages($request, $product);
        $this->storeSpecs($request, $product);

        return back()->with('status', 'Producto actualizado.');
    }

    public function destroy(Request $request, Product $product)
    {
        // El supervisor no elimina: solo quita el producto de su tienda
        if ($scoped = $request->user()->scopedBranchId()) {
            $product->branches()->detach($scoped);

            return redirect()->route('admin.productos.index')->with('status', "\"{$product->name}\" se quitó de tu tienda.");
        }
        $product->delete();   // soft delete

        return redirect()->route('admin.productos.index')->with('status', "Producto \"{$product->name}\" eliminado.");
    }

    protected function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'new_brand' => ['nullable', 'string', 'max:120'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'gt:base_price'],
            'material' => ['nullable', 'string', 'max:120'],
            'origin_country' => ['nullable', 'string', 'max:80'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'published' => ['boolean'],
            'images.*' => [ImageUpload::rule(6144)],
        ], [
            'compare_at_price.gt' => 'El precio "antes" debe ser mayor al precio actual.',
        ], ['name' => 'nombre', 'category_id' => 'categoría', 'base_price' => 'precio']);

        if (! empty($data['new_brand'])) {
            $brand = Brand::firstOrCreate(['slug' => Str::slug($data['new_brand'])], ['name' => $data['new_brand']]);
            $data['brand_id'] = $brand->id;
        }
        unset($data['new_brand'], $data['images']);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $data['published_at'] = $request->boolean('published') ? ($product?->published_at ?? now()) : null;
        unset($data['published']);

        return $data;
    }

    protected function storeImages(Request $request, Product $product): void
    {
        if (! $request->hasFile('images')) {
            return;
        }
        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $order = (int) $product->images()->max('sort_order');
        foreach ($request->file('images') as $file) {
            $path = ImageUpload::store($file, 'products');
            $product->images()->create([
                'path' => $path,
                'alt' => $product->name,
                'sort_order' => ++$order,
                'is_primary' => ! $hasPrimary,
            ]);
            $hasPrimary = true;
        }
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (Product::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "$base-".$i++;
        }

        return $slug;
    }

    protected function categoryOptions()
    {
        return Category::roots()->with('children')->get()->flatMap(fn ($c) => [
            $c,
            ...$c->children->map(fn ($ch) => tap($ch, fn ($x) => $x->name = '— '.$x->name)),
        ]);
    }
}
