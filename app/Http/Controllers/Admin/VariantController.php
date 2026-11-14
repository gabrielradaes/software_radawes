<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VariantController extends Controller
{
    public function index(Request $request, Product $product)
    {
        $scoped = $request->user()->scopedBranchId();
        $branches = Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->orderByDesc('is_main')->get();
        $branch = $branches->firstWhere('id', (int) $request->query('sucursal')) ?? $branches->first();
        abort_if($scoped && ! $product->branches()->where('branches.id', $scoped)->exists(), 403, 'Este producto no se vende en tu sucursal.');

        $product->load(['variants.attributeValues.attribute', 'variants.stocks']);
        $attributes = Attribute::with('values')->get();

        // Matriz color × talla (o lista plana si no hay talla)
        $rows = $product->variants->map(function (ProductVariant $v) use ($branch) {
            $stock = $v->stocks->firstWhere('branch_id', $branch->id);

            return [
                'variant' => $v,
                'color' => $v->valueOf('color') ?? '—',
                'talla' => $v->valueOf('talla') ?? '—',
                'stock' => $stock,
                'qty' => (int) ($stock->quantity ?? 0),
                'min' => (int) ($stock->min_quantity ?? 0),
            ];
        })->sortBy([['color', 'asc'], ['talla', 'asc']])->values();

        return view('admin.variants.index', compact('product', 'branch', 'branches', 'attributes', 'rows'));
    }

    /** Crea las variantes faltantes para la combinación de valores elegida. */
    public function generate(Request $request, Product $product)
    {
        $data = $request->validate([
            'colores' => ['nullable', 'array'],
            'colores.*' => ['exists:attribute_values,id'],
            'tallas' => ['nullable', 'array'],
            'tallas.*' => ['exists:attribute_values,id'],
            'new_color' => ['nullable', 'string', 'max:80'],
            'new_color_hex' => ['nullable', 'string', 'max:40'],
        ]);

        $colorIds = $data['colores'] ?? [];
        if (! empty($data['new_color'])) {
            $attr = Attribute::firstOrCreate(['slug' => 'color'], ['name' => 'Color']);
            $val = AttributeValue::firstOrCreate(
                ['attribute_id' => $attr->id, 'value' => $data['new_color']],
                ['meta' => $data['new_color_hex'] ?: null, 'sort_order' => 99]
            );
            $colorIds[] = $val->id;
        }
        $tallaIds = $data['tallas'] ?? [];

        if (! $colorIds && ! $tallaIds) {
            return back()->withErrors(['colores' => 'Elige al menos un color o una talla.']);
        }

        $combos = [];
        foreach ($colorIds ?: [null] as $c) {
            foreach ($tallaIds ?: [null] as $t) {
                $combos[] = array_values(array_filter([$c, $t]));
            }
        }

        $existing = $product->variants()->with('attributeValues')->get()
            ->map(fn ($v) => $v->attributeValues->pluck('id')->sort()->values()->all());

        $created = 0;
        DB::transaction(function () use ($combos, $existing, $product, &$created) {
            $base = 'P'.str_pad((string) $product->id, 3, '0', STR_PAD_LEFT);
            foreach ($combos as $ids) {
                sort($ids);
                if ($existing->contains(fn ($e) => $e === $ids)) {
                    continue;
                }
                $values = AttributeValue::whereIn('id', $ids)->get();
                $sku = $base.'-'.$values->map(fn ($v) => strtoupper(substr(Str::slug($v->value), 0, 3)))->implode('-');
                while (ProductVariant::where('sku', $sku)->exists()) {
                    $sku .= '-'.Str::upper(Str::random(2));
                }
                $variant = $product->variants()->create(['sku' => $sku]);
                $variant->attributeValues()->sync($ids);
                $branchIds = $product->branches()->pluck('branches.id');
                foreach ($branchIds->isEmpty() ? Branch::pluck('id') : $branchIds as $bid) {
                    Stock::firstOrCreate(['product_variant_id' => $variant->id, 'branch_id' => $bid], ['quantity' => 0, 'min_quantity' => 1]);
                }
                $created++;
            }
        });

        return back()->with('status', $created ? "Se crearon $created variantes." : 'Todas esas variantes ya existían.');
    }

    /** Guarda cantidades de stock editadas en la grilla (crea movimientos de ajuste). */
    public function saveStock(Request $request, Product $product)
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && (int) $request->branch_id !== $scoped, 403);

        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'qty' => ['array'],
            'qty.*' => ['nullable', 'integer', 'min:0'],
            'min' => ['array'],
            'min.*' => ['nullable', 'integer', 'min:0'],
            'price' => ['array'],
            'price.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $variantIds = $product->variants()->pluck('id');
        $changes = 0;

        DB::transaction(function () use ($data, $variantIds, $request, &$changes) {
            foreach ($data['qty'] ?? [] as $variantId => $qty) {
                if (! $variantIds->contains((int) $variantId)) {
                    continue;
                }
                $stock = Stock::firstOrCreate(
                    ['product_variant_id' => $variantId, 'branch_id' => $data['branch_id']],
                    ['quantity' => 0, 'min_quantity' => 1]
                );
                $min = $data['min'][$variantId] ?? null;
                if ($min !== null && (int) $min !== $stock->min_quantity) {
                    $stock->update(['min_quantity' => (int) $min]);
                }
                $diff = (int) $qty - $stock->quantity;
                if ($diff !== 0) {
                    $stock->move('ajuste', $diff, $request->user()->id, 'Ajuste desde grilla de variantes');
                    $changes++;
                }
                $price = $data['price'][$variantId] ?? null;
                ProductVariant::whereKey($variantId)->update(['price' => $price === null || $price === '' ? null : $price]);
            }
        });

        return back()->with('status', $changes ? "Stock actualizado en $changes variantes." : 'Sin cambios de stock.');
    }

    public function update(Request $request, ProductVariant $variant)
    {
        $variant->update(['is_active' => ! $variant->is_active]);

        return back()->with('status', 'Variante '.($variant->is_active ? 'activada' : 'desactivada').'.');
    }

    public function destroy(ProductVariant $variant)
    {
        if ($variant->saleItems()->exists()) {
            return back()->withErrors(['variant' => 'No se puede eliminar: tiene ventas registradas. Desactívala.']);
        }
        $variant->delete();

        return back()->with('status', 'Variante eliminada.');
    }
}
