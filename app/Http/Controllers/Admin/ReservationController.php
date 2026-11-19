<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Reservation;
use App\Services\OpsService;
use App\Services\SaleService;
use App\Support\VariantOptions;
use Illuminate\Http\Request;
use RuntimeException;

/** Apartados con adelanto. */
class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $estado = $request->query('estado', 'activo');
        $reservations = Reservation::with('customer', 'branch', 'items')
            ->when($scoped, fn ($q) => $q->where('branch_id', $scoped))
            ->when($estado !== 'todos', fn ($q) => $q->where('status', $estado))
            ->orderBy($estado === 'activo' ? 'expires_at' : 'id', $estado === 'activo' ? 'asc' : 'desc')->paginate(30)->withQueryString();
        $active = Reservation::where('status', 'activo')->when($scoped, fn ($q) => $q->where('branch_id', $scoped));

        return view('admin.reservations.index', [
            'reservations' => $reservations, 'estado' => $estado,
            'totals' => ['activos' => (clone $active)->count(), 'vencidos' => (clone $active)->where('expires_at', '<', today())->count(),
                'adelantos' => (float) (clone $active)->sum('deposit'), 'saldo' => (float) (clone $active)->selectRaw('SUM(total - deposit) as s')->value('s')],
        ]);
    }

    public function create(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $branches = Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->orderByDesc('is_main')->get();
        $branch = $branches->firstWhere('id', (int) $request->query('sucursal')) ?? $branches->first();

        return view('admin.reservations.create', [
            'branches' => $branches, 'branch' => $branch, 'scoped' => $scoped,
            'customer' => $request->filled('cliente') ? Customer::find($request->cliente) : null,
        ]);
    }

    public function store(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'deposit' => ['required', 'numeric', 'min:0'],
            'deposit_method' => ['required', 'in:'.implode(',', array_keys(SaleService::PAYMENTS))],
            'expires_at' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['customer_phone' => 'celular del cliente', 'deposit' => 'adelanto', 'expires_at' => 'fecha límite']);
        abort_if($scoped && (int) $data['branch_id'] !== $scoped, 403);

        $phone = preg_replace('/\D+/', '', $data['customer_phone']);
        $customer = Customer::where('phone', $phone)->first();
        if (! $customer) {
            if (empty($data['customer_name'])) {
                return back()->withInput()->withErrors(['customer_name' => 'Ese celular no está registrado: escribe el nombre del cliente.']);
            }
            $customer = Customer::create(['name' => $data['customer_name'], 'phone' => $phone, 'source' => 'tienda', 'is_active' => true, 'branch_id' => $data['branch_id']]);
        }

        try {
            $res = OpsService::reserve($request->user(), Branch::findOrFail($data['branch_id']), $customer,
                collect($data['items'])->map(fn ($i) => ['variant' => ProductVariant::findOrFail($i['variant_id']), 'quantity' => (int) $i['quantity'],
                    'unit_price' => isset($i['price']) && $i['price'] !== '' ? (float) $i['price'] : null])->all(),
                (float) $data['deposit'], $data['deposit_method'], $data['expires_at'], $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('admin.apartados.show', $res)->with('status', "Apartado {$res->code} registrado. Los pares quedan reservados.");
    }

    public function show(Request $request, Reservation $reservation)
    {
        $this->authorizeBranch($request, $reservation);
        $reservation->load('customer', 'branch', 'user', 'sale', 'items.variant.product', 'items.variant.attributeValues.attribute');

        return view('admin.reservations.show', compact('reservation'));
    }

    public function pickUp(Request $request, Reservation $reservation)
    {
        $this->authorizeBranch($request, $reservation);
        $data = $request->validate(['payment_method' => ['required', 'in:'.implode(',', array_keys(SaleService::PAYMENTS))]]);
        try {
            $sale = OpsService::pickUp($reservation, $request->user(), $data['payment_method']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['reservation' => $e->getMessage()]);
        }

        return redirect()->route('admin.ventas.show', $sale)->with('status', "Retirado. Venta {$sale->code}: se cobró el saldo de ".money($reservation->balance()).'.');
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        $this->authorizeBranch($request, $reservation);
        try {
            OpsService::cancelReservation($reservation, $request->user(), $request->boolean('refund'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['reservation' => $e->getMessage()]);
        }

        return back()->with('status', 'Apartado cancelado: los pares vuelven a estar a la venta.');
    }

    private function authorizeBranch(Request $request, Reservation $r): void
    {
        $scoped = $request->user()->scopedBranchId();
        abort_if($scoped && $r->branch_id !== $scoped, 403, 'Es un apartado de otra tienda.');
    }
}
