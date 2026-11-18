<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockCount;
use App\Services\OpsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/** Inventario físico: contar escaneando (varios celulares a la vez) y ajustar las diferencias. */
class StockCountController extends Controller
{
    public function index(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();

        return view('admin.counts.index', [
            'counts' => StockCount::with('branch', 'user', 'closer')->withCount('items')->withSum('items', 'counted')
                ->when($scoped, fn ($q) => $q->where('branch_id', $scoped))->latest('id')->paginate(20),
            'branches' => Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->orderByDesc('is_main')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['branch_id' => ['required', 'exists:branches,id'], 'scope' => ['required', 'in:parcial,total'], 'notes' => ['nullable', 'string', 'max:255']]);
        $this->authorizeBranch($request, (int) $data['branch_id']);
        if (StockCount::where('branch_id', $data['branch_id'])->where('status', 'abierto')->exists()) {
            return back()->withErrors(['count' => 'Ya hay un inventario abierto en esa tienda: termínalo o cancélalo primero.']);
        }
        $count = StockCount::create($data + ['user_id' => $request->user()->id]);

        return redirect()->route('admin.inventario.show', $count);
    }

    public function show(Request $request, StockCount $count)
    {
        $this->authorizeBranch($request, $count->branch_id);
        $rows = OpsService::countRows($count);
        $cost = ProductVariant::with('product')->whereIn('id', $rows->pluck('variant.id'))->get()->mapWithKeys(fn ($v) => [$v->id => (float) ($v->product->cost ?? 0)]);

        return view('admin.counts.show', [
            'count' => $count->load('branch', 'user', 'closer'), 'rows' => $rows,
            'summary' => [
                'contados' => $rows->sum('counted'),
                'faltan' => $rows->where('diff', '<', 0)->sum(fn ($r) => -$r['diff']),
                'sobran' => $rows->where('diff', '>', 0)->sum('diff'),
                'valor' => $rows->sum(fn ($r) => $r['diff'] * ($cost[$r['variant']->id] ?? 0)),
            ],
        ]);
    }

    /** Escaneo: suma 1 (o fija la cantidad) de un par. Responde JSON para contar rápido. */
    public function scan(Request $request, StockCount $count)
    {
        $this->authorizeBranch($request, $count->branch_id);
        abort_if($count->status !== 'abierto', 422, 'Este inventario ya está cerrado.');
        $data = $request->validate(['code' => ['required_without:variant_id', 'nullable', 'string', 'max:60'], 'variant_id' => ['nullable', 'exists:product_variants,id'], 'set' => ['nullable', 'integer', 'min:0', 'max:9999']]);

        $variant = ! empty($data['variant_id']) ? ProductVariant::find($data['variant_id'])
            : ProductVariant::whereRaw('UPPER(sku) = ?', [Str::upper(trim($data['code']))])->first();
        if (! $variant) {
            return response()->json(['ok' => false, 'message' => '«'.$data['code'].'» no es un código del sistema.'], 404);
        }
        $item = $count->items()->firstOrCreate(['product_variant_id' => $variant->id], ['counted' => 0]);
        isset($data['set']) ? $item->update(['counted' => $data['set']]) : $item->increment('counted');
        $variant->load('product', 'attributeValues.attribute');

        return response()->json(['ok' => true, 'variant_id' => $variant->id, 'label' => trim(($variant->product->code ? $variant->product->code.' · ' : '').$variant->product->name.' · '.$variant->label()),
            'counted' => $item->fresh()->counted, 'total' => (int) $count->items()->sum('counted')]);
    }

    public function close(Request $request, StockCount $count)
    {
        $this->authorizeBranch($request, $count->branch_id);
        try {
            OpsService::closeCount($count, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['count' => $e->getMessage()]);
        }
        \App\Models\AuditLog::record('inventario', $count, "Inventario físico #{$count->id} cerrado en {$count->branch->name}: stock ajustado");

        return back()->with('status', 'Inventario cerrado: el stock quedó igual a lo contado.');
    }

    public function cancel(Request $request, StockCount $count)
    {
        $this->authorizeBranch($request, $count->branch_id);
        abort_if($count->status !== 'abierto', 422);
        $count->update(['status' => 'cancelado', 'closed_by' => $request->user()->id, 'closed_at' => now()]);

        return redirect()->route('admin.inventario.index')->with('status', 'Inventario cancelado: el stock no se tocó.');
    }

    private function authorizeBranch(Request $request, int $branchId): void
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && $scoped !== $branchId, 403, 'Es de otra tienda.');
    }
}
