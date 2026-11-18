<?php

namespace App\Support;

use App\Models\Stock;
use Illuminate\Support\Collection;

/** Lista de pares disponibles en una tienda para los selectores (venta, apartado, traspaso, cambio). */
class VariantOptions
{
    public static function forBranch(int $branchId): Collection
    {
        return Stock::with('variant.product', 'variant.attributeValues.attribute')
            ->where('branch_id', $branchId)->whereRaw('quantity - reserved > 0')
            ->whereHas('variant', fn ($v) => $v->where('is_active', true)->whereHas('product', fn ($p) => $p->whereNull('deleted_at')))
            ->get()
            ->map(fn ($s) => [
                'id' => $s->product_variant_id,
                'label' => trim(($s->variant->product->code ? $s->variant->product->code.' · ' : '').$s->variant->product->name.' · '.$s->variant->label()),
                'price' => $s->variant->effectivePrice(),
                'stock' => $s->available(),
                'sku' => $s->variant->sku,
            ])->sortBy('label')->values();
    }
}
