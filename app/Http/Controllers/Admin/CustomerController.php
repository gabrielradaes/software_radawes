<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $customers = Customer::withCount(['comments', 'likes', 'whatsappLeads', 'sales'])
            ->when($q, fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%$q%")->orWhere('phone', 'like', "%$q%")->orWhere('email', 'like', "%$q%")->orWhere('document', 'like', "%$q%");
            }))
            ->when($request->filtro === 'registrados', fn ($query) => $query->whereNotNull('password'))
            ->when($request->filtro === 'inactivos', fn ($query) => $query->where('is_active', false))
            ->when($request->filled('etapa'), fn ($query) => $query->where('stage', $request->etapa))
            ->when($request->filled('etiqueta'), fn ($query) => $query->whereHas('tags', fn ($t) => $t->whereKey($request->etiqueta)))
            ->with('tags')
            ->latest()
            ->paginate(25)->withQueryString();

        $totals = [
            'total' => Customer::count(),
            'registrados' => Customer::whereNotNull('password')->count(),
            'semana' => Customer::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        $tags = \App\Models\Tag::orderBy('name')->get();

        return view('admin.customers.index', compact('customers', 'q', 'totals', 'tags'));
    }

    public function create()
    {
        return view('admin.customers.form', ['customer' => new Customer(['city' => 'La Paz'])]);
    }

    /**
     * Registrar un cliente desde la tienda (sin contraseña). Si tiene Gmail, después activa
     * su cuenta web con "¿Olvidaste tu contraseña?" en el sitio.
     */
    public function store(Request $request)
    {
        \App\Support\Gmail::normalize($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s-]{7,20}$/'],
            'email' => ['nullable', 'string', 'max:150', 'email:rfc', 'regex:/^[a-z0-9](?:[a-z0-9.+_-]*[a-z0-9])?@gmail\.com$/', 'unique:customers,email'],
            'city' => ['nullable', 'string', 'max:80'],
            'document' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'email.regex' => \App\Support\Gmail::MESSAGE,
            'email.unique' => 'Ya hay un cliente con ese Gmail.',
            'phone.regex' => 'Escribe un celular válido (solo números).',
        ], ['name' => 'nombre', 'phone' => 'celular', 'email' => 'Gmail', 'document' => 'CI/NIT', 'notes' => 'notas']);

        $data['phone'] = preg_replace('/\D+/', '', $data['phone']);

        if ($existing = Customer::where('phone', $data['phone'])->first()) {
            return redirect()->route('admin.clientes.show', $existing)
                ->with('status', 'Ese celular ya estaba registrado: esta es su ficha.');
        }

        $customer = Customer::create($data + ['is_active' => true]);

        return redirect()->route('admin.clientes.show', $customer)->with('status', 'Cliente registrado.');
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'comments.commentable',
            'likes.likeable',
            'whatsappLeads.product.primaryImage', 'whatsappLeads.variant.attributeValues.attribute', 'whatsappLeads.branch',
            'sales.branch', 'sales.items.variant.product', 'sales.items.variant.attributeValues.attribute',
            'interactions.user', 'tasks.user', 'tags', 'branch',
        ]);

        // Historial unificado (lo más reciente primero)
        $timeline = collect()
            ->merge($customer->interactions->map(fn ($i) => ['at' => $i->created_at, 'icon' => mb_substr($i->typeLabel(), 0, 2), 'title' => trim(mb_substr($i->typeLabel(), 2)).($i->user ? ' · '.$i->user->name : ''), 'body' => $i->body]))
            ->merge($customer->sales->map(fn ($s) => ['at' => $s->sold_at, 'icon' => '🧾', 'title' => "Compra {$s->code} · ".money($s->total).($s->status === 'anulada' ? ' (anulada)' : ''), 'body' => $s->items->map(fn ($it) => $it->quantity.' × '.$it->variant->product->name.' '.$it->variant->label())->implode(', '), 'url' => route('admin.ventas.show', $s)]))
            ->merge($customer->whatsappLeads->map(fn ($l) => ['at' => $l->created_at, 'icon' => '🟢', 'title' => 'Consultó por WhatsApp · '.$l->status, 'body' => $l->product?->name ?? ($l->post?->title ?? 'Consulta general')]))
            ->merge($customer->comments->map(fn ($c) => ['at' => $c->created_at, 'icon' => '💬', 'title' => 'Comentó en '.($c->commentable?->title ?? $c->commentable?->name ?? 'la página'), 'body' => $c->body]))
            ->sortByDesc('at')->values();

        $stats = [
            'spent' => (float) $customer->sales->where('status', 'pagada')->sum('total'),
            'count' => $customer->sales->where('status', 'pagada')->count(),
            'last' => $customer->sales->where('status', 'pagada')->max('sold_at'),
        ];

        return view('admin.customers.show', compact('customer', 'timeline', 'stats'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);
        $customer->update(['notes' => $data['notes'] ?? null, 'is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Cliente actualizado.');
    }
}
