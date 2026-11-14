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
            ->latest()
            ->paginate(25)->withQueryString();

        $totals = [
            'total' => Customer::count(),
            'registrados' => Customer::whereNotNull('password')->count(),
            'semana' => Customer::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return view('admin.customers.index', compact('customers', 'q', 'totals'));
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'comments.commentable',
            'likes.likeable',
            'whatsappLeads.product.primaryImage', 'whatsappLeads.variant.attributeValues.attribute', 'whatsappLeads.branch',
            'sales.branch', 'sales.items.variant.product',
        ]);

        return view('admin.customers.show', compact('customer'));
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
