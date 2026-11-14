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

        $query = Product::published()
            ->inBranch(app(\App\Support\CurrentBranch::class)->get()->id)
            ->with(['primaryImage', 'category'])
            ->withCount('likes')
            ->search($term);

        if ($category) {
            $query->whereIn('category_id', $category->descendantIds());
        }

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

        return view('catalog.index', compact('products', 'category', 'sort', 'term'));
    }
}
