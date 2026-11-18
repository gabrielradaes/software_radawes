<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashSession;
use Illuminate\Http\Request;

/** Caja diaria: abrir con monto inicial, ver ventas del turno y cerrar con arqueo. */
class CashController extends Controller
{
    public function index(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $branches = Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->orderByDesc('is_main')->get();
        $branch = $branches->firstWhere('id', (int) $request->query('sucursal')) ?? $branches->firstWhere('id', $request->user()->branch_id) ?? $branches->first();
        $open = CashSession::openFor($branch->id);

        return view('admin.cash.index', [
            'branch' => $branch, 'branches' => $branches, 'scoped' => $scoped, 'open' => $open,
            'totals' => $open?->totalsByPayment() ?? [],
            'expected' => $open?->expectedCash(),
            'history' => CashSession::with('opener', 'closer')->where('branch_id', $branch->id)->whereNotNull('closed_at')->latest('closed_at')->limit(30)->get(),
        ]);
    }

    public function open(Request $request)
    {
        $data = $request->validate(['branch_id' => ['required', 'exists:branches,id'], 'opening_amount' => ['required', 'numeric', 'min:0']], [], ['opening_amount' => 'monto inicial']);
        $this->authorizeBranch($request, (int) $data['branch_id']);
        if (CashSession::openFor($data['branch_id'])) {
            return back()->withErrors(['caja' => 'La caja de esta tienda ya está abierta.']);
        }
        CashSession::create(['branch_id' => $data['branch_id'], 'opened_by' => $request->user()->id, 'opened_at' => now(), 'opening_amount' => $data['opening_amount']]);

        return back()->with('status', 'Caja abierta. Las ventas de esta tienda se suman a este turno.');
    }

    public function close(Request $request, CashSession $session)
    {
        $this->authorizeBranch($request, $session->branch_id);
        abort_if($session->closed_at, 422, 'Esta caja ya está cerrada.');
        $data = $request->validate(['counted_cash' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:500']], [], ['counted_cash' => 'efectivo contado']);

        $expected = $session->expectedCash();
        $session->update([
            'closed_at' => now(), 'closed_by' => $request->user()->id, 'expected_cash' => $expected, 'counted_cash' => $data['counted_cash'],
            'difference' => $data['counted_cash'] - $expected, 'totals' => $session->totalsByPayment(), 'notes' => $data['notes'] ?? null,
        ]);
        $diff = $data['counted_cash'] - $expected;
        if (abs($diff) >= 0.01) {
            AuditLog::record('caja', $session, "Cierre de caja {$session->branch->name} con diferencia de ".money($diff));
        }

        return back()->with('status', abs($diff) < 0.01 ? 'Caja cerrada: cuadra perfecto ✔' : 'Caja cerrada con una diferencia de '.money($diff).'.');
    }

    private function authorizeBranch(Request $request, int $branchId): void
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && $scoped !== $branchId, 403, 'Es la caja de otra tienda.');
    }
}
