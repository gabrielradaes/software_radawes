<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\CustomerTask;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CRM: seguimientos con fecha, embudo de clientes, notas e historial. */
class CrmController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tasks = CustomerTask::with('customer', 'user')->pending()->visibleTo($user)->where('due_at', '<=', now()->addDays(7)->endOfDay())
            ->orderBy('due_at')->get();
        $groups = [
            'Atrasados' => $tasks->filter(fn ($t) => $t->due_at->lt(now()->startOfDay())),
            'Hoy' => $tasks->filter(fn ($t) => $t->due_at->isToday()),
            'Próximos 7 días' => $tasks->filter(fn ($t) => $t->due_at->gt(now()->endOfDay())),
        ];

        $pipeline = Customer::selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');
        $inactive = Customer::whereHas('sales')->whereDoesntHave('sales', fn ($q) => $q->where('sold_at', '>=', now()->subDays(90)))->count();
        $recent = CustomerInteraction::with('customer', 'user')->latest()->limit(12)->get();
        $top = Customer::withSum(['sales as spent' => fn ($q) => $q->where('status', 'pagada')], 'total')
            ->having('spent', '>', 0)->orderByDesc('spent')->limit(8)->get();
        $staff = $this->staff($user);
        $waiting = \App\Models\RestockRequest::with('customer', 'product', 'branch')->whereNull('notified_at')
            ->when($user->scopedBranchId(), fn ($q, $b) => $q->where('branch_id', $b))->latest()->limit(15)->get();

        return view('admin.crm.index', compact('groups', 'pipeline', 'inactive', 'recent', 'top', 'staff', 'waiting'));
    }

    public function storeTask(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:200'],
            'due_at' => ['required', 'date'],
            'user_id' => ['nullable', Rule::in($this->staff($user)->pluck('id'))],
        ], [], ['title' => 'qué hay que hacer', 'due_at' => 'fecha']);

        $assignee = User::find($data['user_id'] ?? $user->id) ?? $user;
        CustomerTask::create([
            'customer_id' => $data['customer_id'] ?? null, 'user_id' => $assignee->id, 'created_by' => $user->id,
            'branch_id' => $assignee->branch_id ?? $user->branch_id, 'title' => $data['title'], 'due_at' => $data['due_at'],
        ]);

        return back()->with('status', 'Seguimiento agendado.');
    }

    public function toggleTask(Request $request, CustomerTask $task)
    {
        $this->authorizeTask($request, $task);
        $task->update(['done_at' => $task->done_at ? null : now()]);

        return back()->with('status', $task->done_at ? 'Seguimiento hecho ✔' : 'Seguimiento reabierto.');
    }

    public function destroyTask(Request $request, CustomerTask $task)
    {
        $this->authorizeTask($request, $task);
        $task->delete();

        return back()->with('status', 'Seguimiento eliminado.');
    }

    public function storeInteraction(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CustomerInteraction::TYPES))],
            'body' => ['required', 'string', 'max:2000'],
        ], [], ['body' => 'nota']);
        $customer->interactions()->create($data + ['user_id' => $request->user()->id]);
        if ($customer->stage === 'prospecto' && in_array($data['type'], ['llamada', 'whatsapp', 'visita'], true)) {
            $customer->update(['stage' => 'interesado']);
        }

        return back()->with('status', 'Anotado en el historial.');
    }

    /** Datos de CRM del cliente: etapa, origen, talla habitual y etiquetas. */
    public function updateCustomer(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys(Customer::STAGES))],
            'source' => ['nullable', Rule::in(array_keys(Customer::SOURCES))],
            'preferred_size' => ['nullable', 'string', 'max:10'],
            'tags' => ['nullable', 'string', 'max:300'],
        ]);
        $customer->update(collect($data)->except('tags')->all());
        $ids = collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($t) => trim($t))->filter()
            ->map(fn ($t) => Tag::named($t)->id);
        $customer->tags()->sync($ids);

        return back()->with('status', 'Datos de CRM guardados.');
    }

    private function staff(User $user)
    {
        return User::where('is_active', true)
            ->when(! $user->isAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->orderBy('name')->get(['id', 'name', 'branch_id']);
    }

    private function authorizeTask(Request $request, CustomerTask $task): void
    {
        abort_unless(CustomerTask::visibleTo($request->user())->whereKey($task->id)->exists(), 403);
    }
}
