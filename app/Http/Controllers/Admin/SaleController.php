<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Stock;
use App\Services\SaleService;
use Illuminate\Http\Request;
use RuntimeException;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $branches = Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->orderByDesc('is_main')->get();
        $branchId = $scoped ?? ($request->filled('sucursal') ? (int) $request->sucursal : null);
        $from = $request->date('desde') ?? now()->startOfMonth();
        $to = ($request->date('hasta') ?? now())->copy()->endOfDay();

        $base = Sale::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $sales = (clone $base)->with(['branch', 'seller', 'customer', 'items'])
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', '%'.$request->q.'%')
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$request->q.'%')->orWhere('phone', 'like', '%'.$request->q.'%'))))
            ->latest('sold_at')->paginate(30)->withQueryString();

        $paid = fn ($from, $to = null) => (clone $base)->where('status', 'pagada')->where('sold_at', '>=', $from)->when($to, fn ($q) => $q->where('sold_at', '<=', $to));
        $totals = [
            'hoy' => (float) $paid(now()->startOfDay())->sum('total'),
            'semana' => (float) $paid(now()->startOfWeek())->sum('total'),
            'mes' => (float) $paid(now()->startOfMonth())->sum('total'),
            'pares_mes' => (int) \App\Models\SaleItem::whereIn('sale_id', $paid(now()->startOfMonth())->select('id'))->sum('quantity'),
        ];
        // Utilidad del mes: (precio − costo) × pares, solo de lo vendido con costo registrado; el descuento se resta
        $items = \App\Models\SaleItem::whereIn('sale_id', $paid(now()->startOfMonth())->select('id'));
        $totals['utilidad_mes'] = (float) (clone $items)->whereNotNull('unit_cost')->selectRaw('SUM((unit_price - unit_cost) * quantity) as u')->value('u')
            - (float) $paid(now()->startOfMonth())->sum('discount');
        $totals['sin_costo_mes'] = (int) (clone $items)->whereNull('unit_cost')->sum('quantity');

        return view('admin.sales.index', compact('sales', 'branches', 'branchId', 'scoped', 'totals', 'from', 'to'));
    }

    public function create(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $branches = Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->orderByDesc('is_main')->get();
        $branch = $branches->firstWhere('id', (int) $request->query('sucursal')) ?? $branches->first();

        // Variantes con stock en la tienda elegida
        $variants = Stock::with('variant.product', 'variant.attributeValues.attribute')
            ->where('branch_id', $branch->id)->where('quantity', '>', 0)
            ->whereHas('variant', fn ($v) => $v->where('is_active', true)->whereHas('product', fn ($p) => $p->whereNull('deleted_at')))
            ->get()
            ->map(fn ($s) => [
                'id' => $s->product_variant_id,
                'label' => trim(($s->variant->product->code ? $s->variant->product->code.' · ' : '').$s->variant->product->name.' · '.$s->variant->label()),
                'price' => $s->variant->effectivePrice(),
                'stock' => $s->quantity,
                'sku' => $s->variant->sku,
            ])->sortBy('label')->values();

        $customer = $request->filled('cliente') ? Customer::find($request->cliente) : null;

        $cashOpen = (bool) \App\Models\CashSession::openFor($branch->id);

        return view('admin.sales.create', compact('branches', 'branch', 'variants', 'customer', 'scoped', 'cashOpen'));
    }

    public function store(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:'.implode(',', array_keys(SaleService::PAYMENTS))],
            'channel' => ['required', 'in:'.implode(',', array_keys(SaleService::CHANNELS))],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['items.required' => 'Agrega al menos un producto.'], ['customer_phone' => 'celular', 'customer_name' => 'nombre del cliente']);
        abort_if($scoped && (int) $data['branch_id'] !== $scoped, 403);

        $customer = null;
        $phone = preg_replace('/\D+/', '', (string) ($data['customer_phone'] ?? ''));
        if ($phone !== '') {
            $customer = Customer::where('phone', $phone)->first();
            if (! $customer) {
                if (empty($data['customer_name'])) {
                    return back()->withInput()->withErrors(['customer_name' => 'Ese celular no está registrado: escribe el nombre del cliente para registrarlo.']);
                }
                $customer = Customer::create(['name' => $data['customer_name'], 'phone' => $phone, 'source' => 'tienda', 'is_active' => true, 'branch_id' => $data['branch_id']]);
            }
        }

        $lines = collect($data['items'])->map(fn ($i) => [
            'variant' => ProductVariant::findOrFail($i['variant_id']),
            'quantity' => (int) $i['quantity'],
            'unit_price' => isset($i['price']) && $i['price'] !== '' ? (float) $i['price'] : null,
        ])->all();

        try {
            $sale = SaleService::register($request->user(), Branch::findOrFail($data['branch_id']), $customer, $lines,
                $data['payment_method'], $data['channel'], (float) ($data['discount'] ?? 0), $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('admin.ventas.show', $sale)->with('status', "Venta {$sale->code} registrada.");
    }

    public function show(Request $request, Sale $sale)
    {
        $this->authorizeBranch($request, $sale);
        $sale->load('branch', 'seller', 'customer', 'items.variant.product', 'items.variant.attributeValues.attribute',
            'returns.user', 'returns.items.variant.product', 'returns.items.variant.attributeValues.attribute');
        $returned = \App\Models\SaleReturnItem::whereIn('sale_item_id', $sale->items->pluck('id'))->where('direction', 'in')
            ->groupBy('sale_item_id')->selectRaw('sale_item_id, SUM(quantity) as q')->pluck('q', 'sale_item_id');
        $variants = $request->user()->can('sales.create') && $sale->status !== 'anulada' ? \App\Support\VariantOptions::forBranch($sale->branch_id) : collect();

        return view('admin.sales.show', compact('sale', 'returned', 'variants'));
    }

    /** Cambio de talla / devolución sobre esta venta. */
    public function exchange(Request $request, Sale $sale)
    {
        $this->authorizeBranch($request, $sale);
        $data = $request->validate([
            'returns' => ['required', 'array'],
            'returns.*' => ['nullable', 'integer', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'payment_method' => ['required', 'in:'.implode(',', array_keys(SaleService::PAYMENTS))],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $gives = collect($data['items'] ?? [])->filter(fn ($i) => ! empty($i['variant_id']))
            ->map(fn ($i) => ['variant' => ProductVariant::findOrFail($i['variant_id']), 'quantity' => (int) ($i['quantity'] ?? 1)])->values()->all();
        try {
            $ret = \App\Services\OpsService::exchange($sale, $request->user(), $data['returns'], $gives, $data['payment_method'], $data['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['exchange' => $e->getMessage()]);
        }
        $msg = match (true) {
            $ret->balance > 0 => 'Cambio registrado: el cliente pagó '.money($ret->balance).' de diferencia.',
            $ret->balance < 0 => ($ret->type === 'cambio' ? 'Cambio registrado' : 'Devolución registrada').': se le devolvió '.money(-$ret->balance).' al cliente.',
            default => 'Cambio registrado sin diferencia de precio.',
        };

        return back()->with('status', $msg);
    }

    public function cancel(Request $request, Sale $sale)
    {
        $this->authorizeBranch($request, $sale);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);
        try {
            SaleService::cancel($sale, $request->user(), $data['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['sale' => $e->getMessage()]);
        }

        return back()->with('status', "Venta {$sale->code} anulada y stock devuelto.");
    }

    private function authorizeBranch(Request $request, Sale $sale): void
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && $sale->branch_id !== $scoped, 403, 'Esta venta es de otra tienda.');
    }
}
