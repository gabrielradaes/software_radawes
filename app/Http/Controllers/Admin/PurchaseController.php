<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttributeValue;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SizeCurve;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use RuntimeException;

/** Compras al proveedor (notas de ingreso), proveedores y curvas de tallas. */
class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $purchases = Purchase::with('supplier', 'branch', 'user')
            ->when($scoped, fn ($q) => $q->where('branch_id', $scoped))
            ->when($request->filled('proveedor'), fn ($q) => $q->where('supplier_id', $request->proveedor))
            ->latest('purchased_at')->latest('id')->paginate(30)->withQueryString();
        $month = Purchase::when($scoped, fn ($q) => $q->where('branch_id', $scoped))->where('purchased_at', '>=', now()->startOfMonth());

        return view('admin.purchases.index', [
            'purchases' => $purchases,
            'suppliers' => Supplier::orderBy('name')->get(),
            'totals' => ['pares' => (int) (clone $month)->sum('pairs'), 'monto' => (float) (clone $month)->sum('total')],
        ]);
    }

    public function create(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $products = Product::with('variants.attributeValues.attribute', 'category')
            ->when($scoped, fn ($q) => $q->inBranch($scoped))->orderBy('name')->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'label' => ($p->code ? $p->code.' · ' : '').$p->name,
                'cost' => $p->cost !== null ? (float) $p->cost : null,
                'sizes' => $p->variants->map(fn ($v) => $v->valueOf('talla'))->filter()->unique()->sort()->values(),
                'colors' => $p->variants->map(fn ($v) => $v->valueOf('color'))->filter()->unique()->values(),
                'shoe' => $p->category?->root()->slug === 'calzado',
            ]);

        return view('admin.purchases.create', [
            'branches' => Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->orderByDesc('is_main')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'products' => $products,
            'curves' => SizeCurve::with('items')->orderBy('name')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'sizes' => $c->items->pluck('quantity', 'size')]),
            'colors' => AttributeValue::whereHas('attribute', fn ($q) => $q->where('slug', 'color'))->orderBy('sort_order')->pluck('value'),
        ]);
    }

    public function store(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'new_supplier' => ['nullable', 'string', 'max:150'],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'blocks' => ['required', 'array', 'min:1'],
            'blocks.*.product_id' => ['required', 'exists:products,id'],
            'blocks.*.color' => ['nullable', 'string', 'max:60'],
            'blocks.*.sizes' => ['nullable', 'array'],
            'blocks.*.sizes.*' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'blocks.*.quantity' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'blocks.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ], ['blocks.required' => 'Agrega al menos un producto.'], [
            'purchased_at' => 'fecha', 'blocks.*.unit_cost' => 'costo', 'blocks.*.product_id' => 'producto', 'invoice_number' => 'factura',
        ]);
        abort_if($scoped && (int) $data['branch_id'] !== $scoped, 403);

        $supplier = ! empty($data['new_supplier'])
            ? Supplier::firstOrCreate(['name' => trim($data['new_supplier'])], ['is_active' => true])
            : (! empty($data['supplier_id']) ? Supplier::find($data['supplier_id']) : null);

        $blocks = collect($data['blocks'])->map(fn ($b) => [
            'product' => Product::findOrFail($b['product_id']),
            'color' => isset($b['color']) && trim($b['color']) !== '' ? \Illuminate\Support\Str::ucfirst(trim($b['color'])) : null,
            'sizes' => collect($b['sizes'] ?? [])->map(fn ($q) => (int) $q)->all(),
            'quantity' => (int) ($b['quantity'] ?? 0),
            'unit_cost' => (float) $b['unit_cost'],
        ])->all();

        try {
            $purchase = PurchaseService::register($request->user(), Branch::findOrFail($data['branch_id']), $supplier, $blocks,
                $data['invoice_number'] ?? null, $data['purchased_at'], $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['blocks' => $e->getMessage()]);
        }

        return redirect()->route('admin.compras.show', $purchase)->with('status', "Ingreso {$purchase->code} registrado: {$purchase->pairs} pares.");
    }

    public function show(Request $request, Purchase $purchase)
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && $purchase->branch_id !== $scoped, 403);
        $purchase->load('supplier', 'branch', 'user', 'items.variant.product', 'items.variant.attributeValues.attribute');

        // Agrupar: producto + color → tallas
        $groups = $purchase->items->groupBy(fn ($i) => $i->variant->product_id.'|'.($i->variant->valueOf('color') ?? ''))
            ->map(fn ($items) => [
                'product' => $items->first()->variant->product,
                'color' => $items->first()->variant->valueOf('color'),
                'sizes' => $items->mapWithKeys(fn ($i) => [$i->variant->valueOf('talla') ?? 'Única' => $i->quantity])->sortKeys(),
                'cost' => $items->first()->unit_cost,
                'pairs' => $items->sum('quantity'),
                'total' => $items->sum('line_total'),
            ])->values();

        return view('admin.purchases.show', compact('purchase', 'groups'));
    }

    // ------------------------------------------------------------------ proveedores

    public function suppliers()
    {
        return view('admin.purchases.suppliers', ['suppliers' => Supplier::withCount('purchases')->withSum('purchases', 'total')->orderBy('name')->get()]);
    }

    public function storeSupplier(Request $request)
    {
        Supplier::create($this->supplierData($request) + ['is_active' => true]);

        return back()->with('status', 'Proveedor agregado.');
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        $supplier->update($this->supplierData($request) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Proveedor actualizado.');
    }

    private function supplierData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['name' => 'nombre']);
    }

    // ------------------------------------------------------------------ curvas de tallas

    public function curves()
    {
        return view('admin.purchases.curves', ['curves' => SizeCurve::with('items')->orderBy('name')->get()]);
    }

    public function storeCurve(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'sizes' => ['required', 'array'],
            'sizes.*' => ['nullable', 'integer', 'min:0', 'max:99'],
        ], [], ['name' => 'nombre']);
        $sizes = collect($data['sizes'])->filter(fn ($q) => (int) $q > 0);
        if ($sizes->isEmpty()) {
            return back()->withInput()->withErrors(['sizes' => 'Pon cuántos pares de al menos una talla.']);
        }
        $curve = SizeCurve::create(['name' => $data['name']]);
        foreach ($sizes as $size => $qty) {
            $curve->items()->create(['size' => (string) $size, 'quantity' => (int) $qty]);
        }

        return back()->with('status', 'Curva creada.');
    }

    public function destroyCurve(SizeCurve $curve)
    {
        ensure_can_delete();
        $curve->delete();

        return back()->with('status', 'Curva eliminada.');
    }
}
