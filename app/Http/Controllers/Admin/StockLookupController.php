<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Consulta rápida de stock (pensada para decenas de miles de registros):
 *  - buscar: modelos por código, nombre o SKU (máx. 12, con lo disponible en la tienda)
 *  - modelo: grilla colores × tallas de un modelo, en mi tienda y en las otras
 *  - sku:    un par por su código de barras
 * Todo responde JSON y lo usan la pantalla de Stock, Nueva venta, Apartados y Traspasos.
 */
class StockLookupController extends Controller
{
    private function branchId(Request $request): int
    {
        return $request->user()->scopedBranchId()
            ?? ((int) $request->query('sucursal') ?: (int) Branch::active()->where('is_main', true)->value('id') ?: (int) Branch::value('id'));
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $branchId = $this->branchId($request);
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $like = str_replace(['%', '_'], ['\%', '\_'], $q);

        // Disponible por modelo en esta tienda (una sola consulta agregada)
        $available = DB::table('stocks')->join('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->where('stocks.branch_id', $branchId)->where('product_variants.is_active', true)
            ->groupBy('product_variants.product_id')
            ->selectRaw('product_variants.product_id, SUM(GREATEST(stocks.quantity - stocks.reserved, 0)) as disponible');

        $products = Product::query()->whereNull('products.deleted_at')
            ->leftJoinSub($available, 'av', 'av.product_id', '=', 'products.id')
            ->where(fn ($w) => $w->where('products.code', 'like', $like.'%')
                ->orWhere('products.name', 'like', '%'.$like.'%')
                ->orWhereIn('products.id', ProductVariant::select('product_id')->where('sku', 'like', $like.'%')))
            ->with('primaryImage')
            ->orderByRaw('CASE WHEN products.code = ? THEN 0 WHEN products.code LIKE ? THEN 1 ELSE 2 END', [$q, $like.'%'])
            ->orderByDesc('av.disponible')->orderBy('products.name')
            ->limit(12)->get(['products.id', 'products.code', 'products.name', 'products.base_price', 'av.disponible']);

        return response()->json($products->map(fn ($p) => [
            'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'price' => (float) $p->base_price,
            'available' => (int) ($p->disponible ?? 0), 'image' => $p->primaryImage?->url(),
        ]));
    }

    /** Grilla de un modelo: colores × tallas, disponible (y apartado) por tienda. */
    public function model(Request $request, Product $product)
    {
        $branchId = $this->branchId($request);
        $product->load(['primaryImage', 'category', 'variants' => fn ($q) => $q->where('is_active', true), 'variants.attributeValues.attribute', 'variants.stocks']);
        $product->variants->each->setRelation('product', $product);   // evita una consulta por variante (precio)
        $branches = Branch::active()->orderByRaw('id = ? desc', [$branchId])->orderByDesc('is_main')->get(['id', 'name', 'slug']);

        $sizes = [];
        $colors = [];
        $cells = [];   // [branchId][color][size] => {...}
        foreach ($product->variants as $v) {
            $color = $v->valueOf('color') ?? 'Único';
            $size = $v->valueOf('talla') ?? 'Única';
            $sizes[$size] = true;
            $colors[$color] ??= $v->attributeValues->firstWhere('attribute.slug', 'color')?->meta;
            foreach ($branches as $b) {
                $s = $v->stocks->firstWhere('branch_id', $b->id);
                $cells[$b->id][$color][$size] = [
                    'variant_id' => $v->id, 'sku' => $v->sku, 'price' => $v->effectivePrice(),
                    'available' => $s ? $s->available() : 0, 'reserved' => (int) ($s->reserved ?? 0),
                ];
            }
        }
        $sizes = array_map('strval', array_keys($sizes));   // "40" no debe volverse número
        usort($sizes, fn ($a, $b) => (is_numeric($a) && is_numeric($b)) ? (int) $a <=> (int) $b : strcmp($a, $b));

        return response()->json([
            'product' => ['id' => $product->id, 'code' => $product->code, 'name' => $product->name, 'price' => (float) $product->base_price,
                'image' => $product->primaryImage?->url(), 'category' => $product->category?->name,
                'edit' => $request->user()->can('products.manage') ? route('admin.productos.variantes', $product) : null],
            'branch_id' => $branchId,
            'branches' => $branches->map(fn ($b) => ['id' => $b->id, 'name' => trim(\Illuminate\Support\Str::afterLast($b->name, '·')) ?: $b->name]),
            'colors' => collect($colors)->map(fn ($hex, $name) => ['name' => $name, 'hex' => $hex])->values(),
            'sizes' => $sizes,
            'cells' => $cells,
        ]);
    }

    /** Un par por su código de barras (para escanear). */
    public function sku(Request $request)
    {
        $code = mb_strtoupper(trim((string) $request->query('code', '')));
        $branchId = $this->branchId($request);
        $v = ProductVariant::with('product', 'attributeValues.attribute')->where('sku', $code)->where('is_active', true)->first();
        if (! $v) {
            return response()->json(['ok' => false, 'message' => "«{$code}» no es un código del sistema."], 404);
        }
        $stock = Stock::where('product_variant_id', $v->id)->where('branch_id', $branchId)->first();

        return response()->json(['ok' => true, 'variant_id' => $v->id, 'sku' => $v->sku, 'product_id' => $v->product_id,
            'label' => trim(($v->product->code ? $v->product->code.' · ' : '').$v->product->name.' · '.$v->label()),
            'price' => $v->effectivePrice(), 'available' => $stock ? $stock->available() : 0]);
    }
}
