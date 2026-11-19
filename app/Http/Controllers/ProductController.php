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
            'variants' => fn ($q) => $q->where('is_active', true)->with(['attributeValues.attribute', 'stocks']),
            'approvedComments.replies', 'specs', 'branches',
        ])->loadCount('likes');

        // Lo que se ofrece en la web: disponible menos lo que cada tienda se reserva (web_stock_buffer).
        // Al cliente NO se le muestran cantidades exactas, solo un nivel: 0 agotado, 1 últimos pares, 2 disponible.
        $otherBranches = $product->branches->where('is_active', true)->where('pivot.is_active', true)->where('id', '!=', $branch->id)->values();
        $webQty = fn ($stock, $b) => $stock ? max(0, $stock->available() - (int) $b->web_stock_buffer) : 0;
        $level = fn (int $q) => $q <= 0 ? 0 : ($q <= 2 ? 1 : 2);

        // Estructura para el selector: colores → tallas → stock
        $matrix = [];
        $colors = [];
        $sizes = [];
        $product->variants->each->setRelation('product', $product);   // el precio de cada variante usa el producto: sin consultas extra
        foreach ($product->variants as $v) {
            $c = $v->valueOf('color') ?? '—';
            $t = $v->valueOf('talla') ?? '—';
            $qty = $webQty($v->stocks->firstWhere('branch_id', $branch->id), $branch);
            $elsewhere = $otherBranches->filter(fn ($b) => $webQty($v->stocks->firstWhere('branch_id', $b->id), $b) > 0)
                ->map(fn ($b) => ['name' => $b->name, 'url' => route('products.show', ['branch' => $b->slug, 'product' => $product->slug])])->values();
            $matrix[$c][$t] = ['id' => $v->id, 'stock' => $level($qty), 'price' => $v->effectivePrice(), 'elsewhere' => $elsewhere];
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

        $totalStock = $product->variants->sum(fn ($v) => $webQty($v->stocks->firstWhere('branch_id', $branch->id), $branch));

        return view('products.show', compact(
            'product', 'branch', 'matrix', 'colors', 'sizes', 'imagesByColor', 'liked', 'related', 'totalStock'
        ));
    }
}
