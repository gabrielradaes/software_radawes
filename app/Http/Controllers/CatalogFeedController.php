<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Support\Str;

/**
 * Catálogo de productos para Facebook/Instagram (Meta Commerce Manager) y Google Merchant Center.
 * Formato RSS 2.0 con el espacio de nombres g: (lo aceptan ambos). Una fila por variante (talla/color)
 * agrupadas por modelo con item_group_id. Gratis: se carga la URL una vez y ellos la leen solos cada día.
 */
class CatalogFeedController extends Controller
{
    public function catalog()
    {
        $main = Branch::active()->where('is_main', true)->first() ?? Branch::active()->first();
        $products = Product::published()->with(['images', 'brand', 'category', 'branches' => fn ($q) => $q->where('branches.is_active', true)->orderByDesc('is_main'),
            'variants' => fn ($q) => $q->where('is_active', true), 'variants.attributeValues.attribute'])
            ->whereHas('images')->get();
        $available = Stock::whereIn('product_variant_id', $products->flatMap->variants->pluck('id'))
            ->selectRaw('product_variant_id, SUM(quantity - reserved) as q')->groupBy('product_variant_id')->pluck('q', 'product_variant_id');

        $items = [];
        foreach ($products as $p) {
            $store = $p->branches->first() ?? $main;
            if (! $store) {
                continue;
            }
            $link = route('products.show', ['branch' => $store->slug, 'product' => $p->slug]);
            $images = $p->images->map(fn ($i) => $i->url())->values();
            $description = Str::limit(trim(strip_tags($p->description ?: $p->short_description ?: $p->name.' de '.($p->material ?: 'cuero').', hecho en '.($p->origin_country ?: 'Brasil').'.')), 4900);
            $variants = $p->variants->isNotEmpty() ? $p->variants : collect([null]);
            foreach ($variants as $v) {
                $qty = $v ? (int) ($available[$v->id] ?? 0) : 0;
                $size = $v?->valueOf('talla');
                $color = $v?->valueOf('color');
                $price = $v ? $v->effectivePrice() : (float) $p->base_price;
                $items[] = [
                    'id' => $v?->sku ?? 'P'.$p->id,
                    'group' => $p->code ?: 'P'.$p->id,
                    'title' => Str::limit(trim($p->name.($color ? " {$color}" : '').($size ? " talla {$size}" : '')), 150, ''),
                    'description' => $description,
                    'link' => $link,
                    'image' => $images[0] ?? null,
                    'extra_images' => $images->slice(1, 9)->all(),
                    'availability' => $qty > 0 ? 'in stock' : 'out of stock',
                    'price' => $p->hasDiscount() ? number_format((float) $p->compare_at_price, 2, '.', '').' BOB' : number_format($price, 2, '.', '').' BOB',
                    'sale_price' => $p->hasDiscount() ? number_format($price, 2, '.', '').' BOB' : null,
                    'brand' => $p->brand?->name ?? $store->brandName(),
                    'size' => $size,
                    'color' => $color,
                    'category' => $p->category?->root()->slug === 'calzado' ? 'Apparel & Accessories > Shoes' : $p->category?->name,
                    'material' => $p->material,
                ];
            }
        }

        return response()->view('feeds.catalog', ['items' => $items, 'store' => $main], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
