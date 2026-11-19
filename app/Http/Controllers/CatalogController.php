<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        return $this->render($request, null);
    }

    public function category(Request $request, Category $category)
    {
        abort_unless($category->is_active, 404);

        return $this->render($request, $category);
    }

    protected function render(Request $request, ?Category $category)
    {
        $sort = $request->string('orden', 'nuevos')->toString();
        $term = trim((string) $request->query('q', ''));
        $branchId = app(\App\Support\CurrentBranch::class)->get()->id;
        $talla = trim((string) $request->query('talla', ''));
        $color = trim((string) $request->query('color', ''));

        $query = Product::published()
            ->inBranch($branchId)
            ->with(['primaryImage', 'category'])
            ->withCount('likes')
            ->search($term);

        if ($category) {
            $query->whereIn('category_id', $category->descendantIds());
        }

        // Talla y color: solo productos que tengan ese par disponible en esta tienda
        $withValue = fn ($q, string $slug, string $value) => $q->whereHas('attributeValues', fn ($a) => $a->where('value', $value)->whereHas('attribute', fn ($x) => $x->where('slug', $slug)));
        if ($talla !== '' || $color !== '') {
            $query->whereHas('variants', function ($v) use ($talla, $color, $branchId, $withValue) {
                $v->where('is_active', true)
                    ->whereHas('stocks', fn ($s) => $s->where('branch_id', $branchId)->whereRaw('quantity - reserved > 0'))
                    ->when($talla !== '', fn ($q) => $withValue($q, 'talla', $talla))
                    ->when($color !== '', fn ($q) => $withValue($q, 'color', $color));
            });
        }

        // Opciones de filtro: tallas y colores con stock en esta tienda
        $available = \App\Models\AttributeValue::with('attribute')
            ->whereHas('variants', fn ($v) => $v->where('is_active', true)
                ->whereHas('stocks', fn ($s) => $s->where('branch_id', $branchId)->whereRaw('quantity - reserved > 0'))
                ->whereHas('product', fn ($p) => $p->published()->when($category, fn ($q) => $q->whereIn('category_id', $category->descendantIds()))))
            ->orderBy('sort_order')->get()->groupBy(fn ($v) => $v->attribute->slug);
        $sizes = ($available['talla'] ?? collect())->pluck('value')->sortBy(fn ($v) => (int) $v)->values();
        $colors = ($available['color'] ?? collect())->map(fn ($v) => ['name' => $v->value, 'hex' => $v->meta])->values();

        if ($request->boolean('oferta')) {
            $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'base_price');
        }

        match ($sort) {
            'precio-asc' => $query->orderBy('base_price'),
            'precio-desc' => $query->orderByDesc('base_price'),
            'populares' => $query->orderByDesc('likes_count'),
            default => $query->orderByDesc('published_at'),
        };

        $products = $query->paginate(12)->withQueryString();

        return view('catalog.index', compact('products', 'category', 'sort', 'term', 'sizes', 'colors', 'talla', 'color'));
    }
}
