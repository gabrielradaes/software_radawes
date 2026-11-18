<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\CurrentBranch;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, CurrentBranch $current)
    {
        abort_unless($product->is_active && $product->published_at && $product->published_at->isPast(), 404);

        $branch = $current->get();

        $product->load([
            'images', 'category.parent', 'brand',
            'variants' => fn ($q) => $q->where('is_active', true)->with([
                'attributeValues.attribute',
                'stocks' => fn ($s) => $s->where('branch_id', $branch->id),
            ]),
            'approvedComments.replies', 'specs',
        ])->loadCount('likes');

        // Estructura para el selector: colores → tallas → stock
        $matrix = [];
        $colors = [];
        $sizes = [];
        foreach ($product->variants as $v) {
            $c = $v->valueOf('color') ?? '—';
            $t = $v->valueOf('talla') ?? '—';
            $qty = $v->stocks->first()?->available() ?? 0;
            $matrix[$c][$t] = ['id' => $v->id, 'sku' => $v->sku, 'stock' => $qty, 'price' => $v->effectivePrice()];
            if (! isset($colors[$c])) {
                $colors[$c] = $v->attributeValues->firstWhere('attribute.slug', 'color')?->meta;
            }
            $sizes[$t] = true;
        }
        $sizes = array_keys($sizes);
        natsort($sizes);
        $sizes = array_values($sizes);

        $imagesByColor = $product->images->mapWithKeys(function ($img) use ($colors) {
            foreach (array_keys($colors) as $c) {
                if (str_contains(mb_strtolower($img->alt ?? ''), mb_strtolower($c))) {
                    return [$c => $img->url()];
                }
            }
            return [];
        });

        $liked = $product->isLikedBy($request->attributes->get('visitor_token'), null, $request->user('customer')?->id);

        $related = Product::published()->inBranch($branch->id)->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)->with('primaryImage')->withCount('likes')->inRandomOrder()->take(4)->get();

        $totalStock = $product->variants->sum(fn ($v) => $v->stocks->first()?->available() ?? 0);

        return view('products.show', compact(
            'product', 'branch', 'matrix', 'colors', 'sizes', 'imagesByColor', 'liked', 'related', 'totalStock'
        ));
    }
}
