<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\Transfer;
use App\Services\OpsService;
use App\Support\VariantOptions;
use Illuminate\Http\Request;
use RuntimeException;

/** Traspasos entre tiendas: envía el origen, confirma el destino. */
class TransferController extends Controller
{
    public function index(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $transfers = Transfer::with('from', 'to', 'sender', 'receiver')
            ->when($scoped, fn ($q) => $q->where(fn ($w) => $w->where('from_branch_id', $scoped)->orWhere('to_branch_id', $scoped)))
            ->orderByRaw("CASE WHEN status = 'enviado' THEN 0 ELSE 1 END")->latest('id')->paginate(30);

        return view('admin.transfers.index', compact('transfers', 'scoped'));
    }

    public function create(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $origins = Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->orderByDesc('is_main')->get();
        $from = $origins->firstWhere('id', (int) $request->query('desde')) ?? $origins->first();

        return view('admin.transfers.create', [
            'origins' => $origins, 'from' => $from, 'scoped' => $scoped,
            'destinations' => Branch::active()->whereKeyNot($from->id)->get(),
            'variants' => VariantOptions::forBranch($from->id),
        ]);
    }

    public function store(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $data = $request->validate([
            'from_branch_id' => ['required', 'exists:branches,id'],
            'to_branch_id' => ['required', 'exists:branches,id', 'different:from_branch_id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['to_branch_id.different' => 'Elige otra tienda de destino.'], ['to_branch_id' => 'tienda de destino']);
        abort_if($scoped && (int) $data['from_branch_id'] !== $scoped, 403, 'Solo puedes enviar desde tu tienda.');

        try {
            $tr = OpsService::sendTransfer($request->user(), Branch::findOrFail($data['from_branch_id']), Branch::findOrFail($data['to_branch_id']),
                collect($data['items'])->map(fn ($i) => ['variant' => ProductVariant::with('product')->findOrFail($i['variant_id']), 'quantity' => (int) $i['quantity']])->all(),
                $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('admin.traspasos.show', $tr)->with('status', "Traspaso {$tr->code} enviado: {$tr->pairs} pares en camino. La otra tienda debe confirmar que los recibió.");
    }

    public function show(Request $request, Transfer $transfer)
    {
        $this->authorizeAny($request, $transfer);
        $transfer->load('from', 'to', 'sender', 'receiver', 'items.variant.product', 'items.variant.attributeValues.attribute');

        return view('admin.transfers.show', compact('transfer'));
    }

    public function receive(Request $request, Transfer $transfer)
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && $transfer->to_branch_id !== $scoped, 403, 'Solo la tienda que recibe puede confirmarlo.');
        try {
            OpsService::receiveTransfer($transfer, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('status', "Recibido: los {$transfer->pairs} pares ya están en el stock de {$transfer->to->name}.");
    }

    public function cancel(Request $request, Transfer $transfer)
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && $transfer->from_branch_id !== $scoped, 403, 'Solo la tienda que envió puede cancelarlo.');
        try {
            OpsService::cancelTransfer($transfer, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('status', 'Traspaso cancelado: los pares volvieron a la tienda de origen.');
    }

    private function authorizeAny(Request $request, Transfer $t): void
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && ! in_array($scoped, [$t->from_branch_id, $t->to_branch_id], true), 403);
    }
}
