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

        $stocks = Stock::with(['variant.product.primaryImage', 'variant.product.category', 'variant.attributeValues.attribute'])
            ->where('branch_id', $branch->id)
            ->whereHas('variant', function ($q) use ($request) {
                $q->where('is_active', true)
                  ->whereHas('product', function ($p) use ($request) {
                      $p->whereNull('deleted_at')
                        ->when($request->filled('q'), fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')))
                        ->when($request->filled('categoria'), fn ($x) => $x->where('category_id', $request->categoria));
                  })
                  ->when($request->filled('q'), fn ($x) => $x->orWhere('sku', 'like', '%'.$request->q.'%'));
            })
            ->when($request->boolean('bajo'), fn ($q) => $q->whereColumn('quantity', '<=', 'min_quantity'))
            ->when($request->boolean('con_stock'), fn ($q) => $q->where('quantity', '>', 0))
            ->join('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->orderBy('products.name')->orderBy('product_variants.sku')
            ->select('stocks.*')
            ->paginate(40)->withQueryString();

        $totals = [
            'unidades' => Stock::where('branch_id', $branch->id)->sum('quantity'),
            'bajo' => Stock::where('branch_id', $branch->id)->whereColumn('quantity', '<=', 'min_quantity')->count(),
            'agotado' => Stock::where('branch_id', $branch->id)->where('quantity', 0)->count(),
        ];

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
