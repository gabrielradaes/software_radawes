<?php

namespace App\Http\Controllers;

use App\Assistant\Parser;
use App\Models\AssistantMessage;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\ActivityLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * API de la app del celular (/app): misma sesión, mismos usuarios y permisos que el panel.
 * Las búsquedas de stock, el ingreso de calzado y el asistente usan las rutas del panel que ya responden JSON;
 * aquí está lo que la app necesita además: entrar/salir, datos iniciales, vender y «Mi día».
 */
class MobileAppController extends Controller
{
    /** Quién soy y con qué trabajo. También deja las cookies de sesión y XSRF para lo que sigue. */
    public function me(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['user' => null]);
        }
        $scoped = $user->scopedBranchId();
        $branches = Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->orderByDesc('is_main')->get(['id', 'name', 'slug']);

        return response()->json([
            'user' => [
                'id' => $user->id, 'name' => $user->name, 'first_name' => $user->firstName(), 'role' => $user->role?->label,
                'branch_id' => $user->branch_id, 'scoped' => (bool) $scoped,
            ],
            'can' => [
                'stock' => $user->can('stock.view'),
                'sell' => $user->can('sales.create'),
                'entry' => $user->can('stock.adjust'),
                'new_model' => $user->can('products.manage'),
                'publish' => $user->can('posts.manage'),
                'teach' => $user->can('assistant.teach'),
            ],
            'branches' => $branches->map(fn ($b) => ['id' => $b->id, 'name' => trim(\Illuminate\Support\Str::afterLast($b->name, '·')) ?: $b->name, 'full' => $b->name]),
            'payments' => collect(SaleService::PAYMENTS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'classes' => Category::where('is_active', true)->whereNotNull('parent_id')->orderBy('sort_order')->get(['id', 'name']),
            'brands' => \App\Models\Brand::orderBy('name')->pluck('name'),
            'colors' => collect(Parser::COLORS)->map(fn ($c) => \Illuminate\Support\Str::ucfirst(['cafe' => 'café', 'marron' => 'marrón'][$c] ?? $c))->values(),
            'links' => [
                'panel' => route('admin.dashboard'),
                'publish' => route('admin.publicaciones.create'),
                'teach' => route('admin.asistente.ensenar'),
            ],
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']], [], ['email' => 'correo', 'password' => 'contraseña']);
        if (! Auth::attempt(['email' => strtolower(trim($data['email'])), 'password' => $data['password'], 'is_active' => true], true)) {
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos, o el usuario está desactivado.']);
        }
        $request->session()->regenerate();

        return $this->me($request);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    /** Venta desde el celular (mismas reglas que «Nueva venta» del panel). */
    public function sell(Request $request)
    {
        $user = $request->user();
        $scoped = $user->scopedBranchId();
        $data = $request->validate([
            'branch_id' => [$scoped ? 'nullable' : 'required', 'integer', 'exists:branches,id'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:'.implode(',', array_keys(SaleService::PAYMENTS))],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ], ['items.required' => 'Agrega al menos un par.'], ['customer_phone' => 'celular', 'customer_name' => 'nombre del cliente']);
        $branch = Branch::findOrFail($scoped ?? $data['branch_id']);

        $customer = null;
        $phone = preg_replace('/^591/', '', preg_replace('/\D+/', '', (string) ($data['customer_phone'] ?? '')));
        if ($phone !== '') {
            $customer = Customer::where('phone', $phone)->first();
            if (! $customer) {
                if (empty($data['customer_name'])) {
                    throw ValidationException::withMessages(['customer_name' => 'Ese celular no está registrado: escribe el nombre del cliente.']);
                }
                $customer = Customer::create(['name' => trim($data['customer_name']), 'phone' => $phone, 'source' => 'tienda', 'is_active' => true, 'branch_id' => $branch->id]);
            }
        }
        $lines = collect($data['items'])->map(fn ($i) => [
            'variant' => ProductVariant::findOrFail($i['variant_id']),
            'quantity' => (int) $i['quantity'],
            'unit_price' => isset($i['price']) && $i['price'] !== '' ? (float) $i['price'] : null,
        ])->all();

        try {
            $sale = SaleService::register($user, $branch, $customer, $lines, $data['payment_method'], 'tienda', (float) ($data['discount'] ?? 0), 'Desde la app');
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return response()->json([
            'ok' => true, 'code' => $sale->code, 'total' => (float) $sale->total, 'customer' => $sale->customer?->name,
            'url' => route('admin.ventas.show', $sale),
        ]);
    }

    /** «Mi día»: mis ventas de hoy y lo último que hice. */
    public function today(Request $request)
    {
        $user = $request->user();
        $sales = Sale::with('items.variant.product', 'customer')->where('user_id', $user->id)->whereDate('sold_at', today())
            ->where('status', 'pagada')->latest('sold_at')->get();
        $log = AuditLog::where('user_id', $user->id)->where('created_at', '>=', now()->subDays(7))->latest('id')->limit(30)->get();

        return response()->json([
            'sales' => [
                'count' => $sales->count(),
                'total' => (float) $sales->sum('total'),
                'pairs' => (int) $sales->sum(fn ($s) => $s->items->sum('quantity')),
                'list' => $sales->map(fn ($s) => [
                    'code' => $s->code, 'time' => $s->sold_at->format('H:i'), 'total' => (float) $s->total, 'payment' => SaleService::PAYMENTS[$s->payment_method] ?? $s->payment_method,
                    'customer' => $s->customer?->name,
                    'items' => $s->items->map(fn ($i) => trim(($i->variant?->product?->name ?? 'Producto').' · '.($i->variant?->label() ?? '')).($i->quantity > 1 ? " ×{$i->quantity}" : ''))->all(),
                ]),
            ],
            'activity' => $log->map(fn ($a) => [
                'icon' => $a->icon(), 'text' => $a->description ?: ucfirst($a->action), 'when' => $a->created_at->diffForHumans(),
                'day' => $a->created_at->isToday() ? 'Hoy' : $a->created_at->translatedFormat('l j'), 'time' => $a->created_at->format('H:i'),
            ]),
        ]);
    }

    /** Conversación con el asistente (los mensajes se envían a las mismas rutas del panel). */
    public function assistant(Request $request)
    {
        $messages = AssistantMessage::where('user_id', $request->user()->id)->whereNull('hidden_at')->latest('id')->limit(30)->get()->reverse()->values();
        $pending = ($request->session()->get('assistant.state')['step'] ?? null) === 'confirm';

        return response()->json([
            'pending' => $pending,
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id, 'role' => $m->role, 'text' => $m->text, 'table' => $m->meta['table'] ?? null,
                'links' => $m->meta['links'] ?? [], 'confirm' => (bool) ($m->meta['confirm'] ?? false), 'choices' => $m->meta['choices'] ?? [],
                'time' => $m->created_at?->format('H:i'),
            ]),
        ]);
    }
}
