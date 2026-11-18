<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Stock;
use Illuminate\Http\Request;

/** Etiquetas con código de barras para imprimir en hojas de stickers (A4). */
class LabelController extends Controller
{
    public function index(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $lines = collect();   // [variant, cantidad]
        $title = 'Etiquetas';

        if ($request->filled('ingreso')) {
            $purchase = Purchase::with('items.variant')->findOrFail($request->ingreso);
            abort_if($scoped && $purchase->branch_id !== $scoped, 403);
            $lines = $purchase->items->map(fn ($i) => [$i->product_variant_id, $i->quantity]);
            $title = "Etiquetas del ingreso {$purchase->code}";
        } elseif ($request->filled('producto')) {
            $product = Product::findOrFail($request->producto);
            $branchId = $scoped ?? ($request->filled('sucursal') ? (int) $request->sucursal : null);
            $mode = $request->query('cantidad', 'stock');   // stock = una por par en stock; una = una por variante
            $lines = $product->variants()->where('is_active', true)->get()->map(function ($v) use ($branchId, $mode) {
                $qty = $mode === 'una' ? 1 : (int) Stock::where('product_variant_id', $v->id)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->sum('quantity');

                return [$v->id, $qty];
            })->filter(fn ($l) => $l[1] > 0);
            $title = "Etiquetas de {$product->name}";
        }

        $variants = ProductVariant::with('product', 'attributeValues.attribute')->whereIn('id', $lines->pluck(0))->get()->keyBy('id');
        $labels = $lines->flatMap(fn ($l) => isset($variants[$l[0]]) ? array_fill(0, min(500, $l[1]), $variants[$l[0]]) : [])
            ->sortBy(fn ($v) => $v->product->name.'|'.$v->valueOf('color').'|'.str_pad((string) $v->valueOf('talla'), 3, '0', STR_PAD_LEFT))->values();

        $brand = ($scoped ? Branch::find($scoped) : Branch::active()->where('is_main', true)->first())?->brandName() ?? 'RadaWes';

        return view('admin.labels.index', compact('labels', 'title', 'brand'));
    }
}
