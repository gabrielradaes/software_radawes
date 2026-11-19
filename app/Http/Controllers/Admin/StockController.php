<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branches = Branch::active()->orderByDesc('is_main')->get();
        $scoped = $user->scopedBranchId();
        $branch = $scoped
            ? $branches->firstWhere('id', $scoped)
            : ($branches->firstWhere('id', (int) $request->query('sucursal')) ?? $branches->first());

        // Solo JOINs (sin subconsultas "exists"): rápido aunque haya decenas de miles de filas de stock
        $q = trim((string) $request->query('q', ''));
        $stocks = Stock::with(['variant.product.primaryImage', 'variant.product.category', 'variant.attributeValues.attribute'])
            ->join('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('stocks.branch_id', $branch->id)
            ->where('product_variants.is_active', true)
            ->whereNull('products.deleted_at')
            ->when($request->filled('categoria'), fn ($x) => $x->where('products.category_id', $request->categoria))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('product_variants.sku', 'like', $q.'%')
                ->orWhere('products.code', 'like', $q.'%')->orWhere('products.name', 'like', '%'.$q.'%')))
            ->when($request->boolean('bajo'), fn ($x) => $x->whereColumn('stocks.quantity', '<=', 'stocks.min_quantity'))
            ->when($request->boolean('con_stock'), fn ($x) => $x->where('stocks.quantity', '>', 0))
            // Sin filtros: lo más reciente primero (usa el índice y no ordena miles de filas); con búsqueda: por nombre
            ->when($q !== '' || $request->filled('categoria'),
                fn ($x) => $x->orderBy('products.name')->orderBy('product_variants.sku'),
                fn ($x) => $x->orderByDesc('stocks.id'))
            // Sin filtros, recorrer primero el stock de la tienda (índice) y cortar en la página: evita ordenar miles de filas
            ->when($q === '' && ! $request->filled('categoria'), fn ($x) => $x->selectRaw('STRAIGHT_JOIN stocks.*'), fn ($x) => $x->select('stocks.*'))
            ->paginate(40)->withQueryString();

        // Totales de la tienda (2 minutos en caché: con decenas de miles de filas no hace falta recalcular en cada visita)
        $totals = \Illuminate\Support\Facades\Cache::remember("stock:totales:{$branch->id}", 120, function () use ($branch) {
            $t = Stock::where('branch_id', $branch->id)
                ->selectRaw('COALESCE(SUM(quantity), 0) as unidades, SUM(CASE WHEN quantity <= min_quantity THEN 1 ELSE 0 END) as bajo, SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as agotado')
                ->first();
            // Inventario valorizado (a costo y a precio de venta)
            $value = Stock::join('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereNull('products.deleted_at')->where('stocks.branch_id', $branch->id)->where('stocks.quantity', '>', 0)
                ->selectRaw('SUM(stocks.quantity * COALESCE(products.cost, 0)) as costo, SUM(stocks.quantity * COALESCE(product_variants.price, products.base_price)) as venta, SUM(CASE WHEN products.cost IS NULL THEN stocks.quantity ELSE 0 END) as sin_costo')
                ->first();

            return ['unidades' => (int) $t->unidades, 'bajo' => (int) $t->bajo, 'agotado' => (int) $t->agotado,
                'valor_costo' => (float) $value->costo, 'valor_venta' => (float) $value->venta, 'sin_costo' => (int) $value->sin_costo];
        });

        $categories = Category::orderBy('parent_id')->orderBy('sort_order')->get();

        return view('admin.stock.index', compact('stocks', 'branch', 'branches', 'totals', 'categories', 'scoped'));
    }

    /** Vista global (solo admin): cada variante con su stock en cada tienda. */
    public function global(Request $request)
    {
        abort_if($request->user()->scopedBranchId(), 403);

        $branches = Branch::active()->orderByDesc('is_main')->get();

        $variants = \App\Models\ProductVariant::with(['product.primaryImage', 'product.category', 'product.branches', 'attributeValues.attribute', 'stocks'])
            ->where('product_variants.is_active', true)
            ->whereHas('product', function ($p) use ($request) {
                $p->whereNull('deleted_at')
                  ->when($request->filled('q'), fn ($x) => $x->where('name', 'like', '%'.$request->q.'%'))
                  ->when($request->filled('categoria'), fn ($x) => $x->where('category_id', $request->categoria));
            })
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->orderBy('products.name')->orderBy('product_variants.sku')
            ->select('product_variants.*')
            ->paginate(50)->withQueryString();

        $totals = $branches->mapWithKeys(fn ($b) => [$b->id => Stock::where('branch_id', $b->id)->sum('quantity')]);
        $categories = Category::orderBy('parent_id')->orderBy('sort_order')->get();

        return view('admin.stock.global', compact('variants', 'branches', 'totals', 'categories'));
    }

    public function kardex(Request $request, Stock $stock)
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && $stock->branch_id !== $scoped, 403);

        $stock->load(['variant.product', 'variant.attributeValues.attribute', 'branch', 'movements.user']);

        return view('admin.stock.kardex', compact('stock'));
    }

    public function move(Request $request, Stock $stock)
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && $stock->branch_id !== $scoped, 403);

        $data = $request->validate([
            'type' => ['required', 'in:entrada,salida,ajuste'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], ['quantity' => 'cantidad', 'reason' => 'motivo']);

        $delta = match ($data['type']) {
            'entrada' => $data['quantity'],
            'salida' => -$data['quantity'],
            'ajuste' => $data['quantity'] - $stock->quantity,  // ajuste = fijar cantidad exacta
        };

        if ($stock->quantity + $delta < 0) {
            return back()->withErrors(['quantity' => "No hay suficiente stock (actual: {$stock->quantity})."]);
        }

        $stock->move($data['type'], $delta, $request->user()->id, $data['reason'] ?? null);

        return back()->with('status', "Movimiento registrado. Stock actual: {$stock->quantity}.");
    }
}
