<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Crea variantes (color × talla) de un producto con su fila de stock en cada tienda donde se vende. */
class VariantBuilder
{
    /**
     * @param  int[]  $colorIds
     * @param  int[]  $tallaIds
     * @return Collection<int, ProductVariant> todas las variantes de esas combinaciones (nuevas y ya existentes)
     */
    public static function generate(Product $product, array $colorIds, array $tallaIds): Collection
    {
        $combos = [];
        foreach ($colorIds ?: [null] as $c) {
            foreach ($tallaIds ?: [null] as $t) {
                $ids = array_values(array_filter([$c, $t]));
                if ($ids) {
                    sort($ids);
                    $combos[] = $ids;
                }
            }
        }

        $existing = $product->variants()->with('attributeValues')->get()
            ->mapWithKeys(fn ($v) => [implode(',', $v->attributeValues->pluck('id')->sort()->values()->all()) => $v]);

        $result = collect();
        DB::transaction(function () use ($combos, $existing, $product, $result) {
            $base = 'P'.str_pad((string) $product->id, 3, '0', STR_PAD_LEFT);
            $branchIds = $product->branches()->pluck('branches.id');
            if ($branchIds->isEmpty()) {
                $branchIds = Branch::pluck('id');
            }
            foreach ($combos as $ids) {
                if ($found = $existing->get(implode(',', $ids))) {
                    $result->push($found);

                    continue;
                }
                $values = AttributeValue::whereIn('id', $ids)->get();
                $sku = $base.'-'.$values->map(fn ($v) => strtoupper(substr(Str::slug($v->value), 0, 3)))->implode('-');
                while (ProductVariant::where('sku', $sku)->exists()) {
                    $sku .= '-'.Str::upper(Str::random(2));
                }
                $variant = $product->variants()->create(['sku' => $sku]);
                $variant->attributeValues()->sync($ids);
                foreach ($branchIds as $bid) {
                    Stock::firstOrCreate(['product_variant_id' => $variant->id, 'branch_id' => $bid], ['quantity' => 0, 'min_quantity' => 1]);
                }
                $result->push($variant); // wasRecentlyCreated = true
            }
        });

        return $result;
    }

    /** Valor de atributo (Color / Talla) por nombre; lo crea si no existe. */
    public static function value(string $attributeSlug, string $value): AttributeValue
    {
        $attr = Attribute::firstOrCreate(['slug' => $attributeSlug], ['name' => Str::ucfirst($attributeSlug)]);
        $existing = AttributeValue::where('attribute_id', $attr->id)->get()
            ->first(fn ($v) => Str::lower(Str::ascii($v->value)) === Str::lower(Str::ascii($value)));

        return $existing ?? AttributeValue::create([
            'attribute_id' => $attr->id,
            'value' => $value,
            'sort_order' => $attributeSlug === 'talla' && is_numeric($value) ? (int) $value : 99,
        ]);
    }
}
