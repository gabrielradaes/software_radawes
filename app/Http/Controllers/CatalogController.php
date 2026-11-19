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

        // Pares disponibles en esta tienda (una sola consulta con JOIN: rápida aunque haya decenas de miles de filas de stock)
        $inStock = fn () => \Illuminate\Support\Facades\DB::table('stocks')
            ->join('product_variants as pv', 'pv.id', '=', 'stocks.product_variant_id')
            ->where('stocks.branch_id', $branchId)->whereRaw('stocks.quantity > stocks.reserved')->where('pv.is_active', true);
        $valueId = fn (string $slug, string $value) => \App\Models\AttributeValue::where('value', $value)
            ->where('attribute_id', \App\Models\Attribute::where('slug', $slug)->value('id'))->value('id') ?? 0;

        // Talla y color: solo modelos que tengan ese par disponible
        if ($talla !== '' || $color !== '') {
            $ids = $inStock();
            foreach (array_filter(['talla' => $talla, 'color' => $color]) as $slug => $value) {
                $alias = 'av_'.$slug;
                $ids->join("attribute_value_product_variant as {$alias}", "{$alias}.product_variant_id", '=', 'pv.id')
                    ->where("{$alias}.attribute_value_id", $valueId($slug, $value));
            }
            $query->whereIn('products.id', $ids->select('pv.product_id'));
        }

        // Opciones de filtro: tallas y colores con stock en esta tienda (y en la categoría elegida)
        // (se guarda 10 minutos: es la misma lista para todos los visitantes)
        $valueIds = \Illuminate\Support\Facades\Cache::remember("catalogo:filtros:{$branchId}:".($category?->id ?? 'todo'), 600,
            fn () => $inStock()->join('attribute_value_product_variant as avp', 'avp.product_variant_id', '=', 'pv.id')
                ->when($category, fn ($q) => $q->join('products as p', 'p.id', '=', 'pv.product_id')->whereIn('p.category_id', $category->descendantIds()))
                ->distinct()->pluck('avp.attribute_value_id')->all());
        $available = \App\Models\AttributeValue::with('attribute')->whereIn('id', $valueIds)->orderBy('sort_order')->get()->groupBy(fn ($v) => $v->attribute->slug);
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
