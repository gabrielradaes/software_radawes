<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RestockRequest;
use App\Support\CurrentBranch;
use Illuminate\Http\Request;

/** "Avísame cuando llegue mi talla" desde la ficha del producto. */
class RestockController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $branch = app(CurrentBranch::class)->get();
        $request->merge(['phone' => preg_replace('/\D+/', '', (string) $request->input('phone'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^(591)?[67]\d{7}$/'],
            'size' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'max:60'],
        ], ['phone.regex' => 'Escribe un celular de 8 dígitos (ej.: 70011222).'], ['name' => 'nombre', 'phone' => 'celular']);
        $phone = preg_replace('/^591/', '', $data['phone']);

        $customer = auth('customer')->user()
            ?? Customer::firstOrCreate(['phone' => $phone], ['name' => $data['name'], 'source' => 'web', 'is_active' => true, 'branch_id' => $branch->id]);

        $exists = RestockRequest::where('customer_id', $customer->id)->where('product_id', $product->id)->where('branch_id', $branch->id)
            ->where('size', $data['size'] ?? null)->where('color', $data['color'] ?? null)->whereNull('notified_at')->exists();
        if (! $exists) {
            $r = RestockRequest::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'branch_id' => $branch->id,
                'size' => $data['size'] ?? null, 'color' => $data['color'] ?? null]);
            $customer->interactions()->create(['type' => 'nota', 'body' => '🔔 Pidió aviso en la página cuando llegue '.$r->load('product')->label()]);
            if (($customer->stage ?? 'prospecto') === 'prospecto') {
                $customer->update(['stage' => 'interesado']);
            }
        }

        return back()->with('restock', '¡Listo! Te escribiremos por WhatsApp al '.$phone.' apenas llegue '.($data['size'] ? 'la talla '.$data['size'] : 'tu par').'.');
    }
}
