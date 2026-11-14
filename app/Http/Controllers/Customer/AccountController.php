<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Like;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->user('customer');

        $favorites = Product::published()
            ->whereIn('id', Like::where('customer_id', $customer->id)->where('likeable_type', Product::class)->pluck('likeable_id'))
            ->with(['primaryImage', 'category'])->withCount('likes')->get();

        $comments = $customer->comments()->with('commentable')->take(10)->get();
        $leads = $customer->whatsappLeads()->with(['product.primaryImage', 'variant.attributeValues.attribute', 'branch'])->take(10)->get();
        $sales = $customer->sales()->with('items.variant.product', 'branch')->latest('sold_at')->take(10)->get();

        return view('customer.account.index', compact('customer', 'favorites', 'comments', 'leads', 'sales'));
    }

    public function update(Request $request)
    {
        $customer = $request->user('customer');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s-]{7,20}$/'],
            'email' => ['required', 'email', 'max:150', Rule::unique('customers')->ignore($customer)],
            'city' => ['nullable', 'string', 'max:80'],
            'document' => ['nullable', 'string', 'max:40'],
            'birthdate' => ['nullable', 'date', 'before:today'],
        ], [], ['name' => 'nombre', 'phone' => 'celular', 'email' => 'correo', 'document' => 'CI/NIT', 'birthdate' => 'fecha de nacimiento']);

        $data['phone'] = preg_replace('/\D+/', '', $data['phone']);
        $customer->update($data);

        return back()->with('status', 'Datos actualizados.');
    }

    public function password(Request $request)
    {
        $customer = $request->user('customer');

        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ], [], ['current_password' => 'contraseña actual', 'password' => 'nueva contraseña']);

        if (! Hash::check($data['current_password'], $customer->password)) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $customer->update(['password' => $data['password']]);

        return back()->with('status', 'Contraseña cambiada.');
    }
}
