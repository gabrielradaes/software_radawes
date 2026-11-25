<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Historial de actividad: qué hizo cada persona.
 * Admin: todo · Supervisor: su tienda (equipo y clientes) · Cada persona: "Mi actividad".
 */
class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $viewer = $request->user();

        return $this->render($request, AuditLog::query()->visibleTo($viewer), false);
    }

    public function mine(Request $request)
    {
        return $this->render($request, AuditLog::where('user_id', $request->user()->id), true);
    }

    protected function render(Request $request, $query, bool $mine)
    {
        $viewer = $request->user();
        $request->validate([
            'desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date'],
            'usuario' => ['nullable', 'integer'], 'sucursal' => ['nullable', 'integer'],
            'quien' => ['nullable', 'in:equipo,clientes'], 'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query->with(['user.role', 'customer', 'branch'])
            ->when($request->filled('usuario'), fn ($q) => $q->where('user_id', $request->integer('usuario')))
            ->when($request->filled('cliente'), fn ($q) => $q->where('customer_id', $request->integer('cliente')))
            ->when($request->filled('sucursal') && $viewer->isAdmin(), fn ($q) => $q->where('branch_id', $request->integer('sucursal')))
            ->when($request->input('quien') === 'equipo', fn ($q) => $q->whereNotNull('user_id'))
            ->when($request->input('quien') === 'clientes', fn ($q) => $q->whereNull('user_id'))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%'.$request->q.'%'))
            ->when($request->filled('desde'), fn ($q) => $q->where('created_at', '>=', Carbon::parse($request->desde)->startOfDay()))
            ->when($request->filled('hasta'), fn ($q) => $q->where('created_at', '<=', Carbon::parse($request->hasta)->endOfDay()));

        $logs = $query->latest('id')->paginate(60)->withQueryString();

        // Agrupar: la acción arriba y, debajo, los datos que cambió en ese mismo paso
        $groups = collect();
        foreach ($logs->getCollection() as $log) {
            $key = $log->batch ?: 'id'.$log->id;
            if (! $groups->has($key)) {
                $groups->put($key, collect());
            }
            $groups->get($key)->push($log);
        }
        $groups = $groups->map(function ($items) {
            $main = $items->firstWhere('action', 'accion') ?? $items->first();

            return ['main' => $main, 'details' => $items->reject(fn ($l) => $l->is($main))->values()];
        })->values();

        // Personas del equipo que se pueden filtrar
        $people = $mine ? collect() : User::query()
            ->when(! $viewer->isAdmin(), fn ($q) => $q->where('branch_id', $viewer->branch_id)->whereDoesntHave('role', fn ($r) => $r->where('name', 'admin')))
            ->orderBy('name')->get(['id', 'name']);

        return view('admin.activity.index', [
            'groups' => $groups,
            'logs' => $logs,
            'people' => $people,
            'branches' => $viewer->isAdmin() && ! $mine ? Branch::orderBy('name')->get(['id', 'name']) : collect(),
            'mine' => $mine,
            'summary' => $this->summary($mine ? AuditLog::where('user_id', $viewer->id) : AuditLog::query()->visibleTo($viewer)),
        ]);
    }

    /** Cuántas acciones hizo cada persona hoy (para ver de un vistazo quién está trabajando). */
    protected function summary($query): array
    {
        return $query->where('action', 'accion')->where('created_at', '>=', today())
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as n, max(created_at) as last_at')
            ->groupBy('user_id')->orderByDesc('n')->limit(12)->get()
            ->map(fn ($r) => ['user' => User::find($r->user_id), 'n' => (int) $r->n, 'last' => Carbon::parse($r->last_at)])
            ->filter(fn ($r) => $r['user'])->values()->all();
    }
}
