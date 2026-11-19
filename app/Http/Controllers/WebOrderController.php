<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTask;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use App\Services\OpsService;
use App\Support\CurrentBranch;
use Illuminate\Http\Request;
use RuntimeException;

/** El cliente separa un par desde la página: queda como apartado (sin adelanto, 2 días) y la tienda lo confirma por WhatsApp. */
class WebOrderController extends Controller
{
    public const DAYS = 2;

    public const MAX_ACTIVE = 3;

    public function store(Request $request, Product $product)
    {
        $branch = app(CurrentBranch::class)->get();
        $request->merge(['sep_phone' => preg_replace('/^591/', '', preg_replace('/\D+/', '', (string) $request->input('sep_phone')))]);
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'sep_name' => ['required', 'string', 'max:150'],
            'sep_phone' => ['required', 'regex:/^[67]\d{7}$/'],
        ], ['sep_phone.regex' => 'Escribe un celular de 8 dígitos (ej.: 70011222).', 'variant_id.required' => 'Elige tu talla y color.'], ['sep_name' => 'nombre', 'sep_phone' => 'celular']);

        $variant = ProductVariant::where('product_id', $product->id)->where('is_active', true)->find($data['variant_id']);
        abort_unless($variant && $product->is_active, 404);

        $customer = auth('customer')->user()
            ?? Customer::firstOrCreate(['phone' => $data['sep_phone']], ['name' => $data['sep_name'], 'source' => 'web', 'is_active' => true, 'branch_id' => $branch->id]);
        if (Reservation::where('customer_id', $customer->id)->where('status', 'activo')->where('source', 'web')->count() >= self::MAX_ACTIVE) {
            return back()->withInput()->withErrors(['separar' => 'Ya tienes '.self::MAX_ACTIVE.' pares separados. Retíralos o escríbenos por WhatsApp.']);
        }

        // Lo registra el responsable de la tienda (supervisor) o un administrador
        $owner = User::where('is_active', true)->where('branch_id', $branch->id)->whereHas('role', fn ($r) => $r->where('name', Role::SUPERVISOR))->first()
            ?? User::where('is_active', true)->whereHas('role', fn ($r) => $r->where('name', Role::ADMIN))->first();
        abort_unless($owner, 503);

        // Respetar los pares que la tienda se guarda para el mostrador
        $stock = $variant->stocks()->where('branch_id', $branch->id)->first();
        if (! $stock || $stock->available() - (int) $branch->web_stock_buffer < 1) {
            return back()->withInput()->withErrors(['separar' => 'Justo se acabó esa talla en esta tienda. Pide aviso y te escribimos cuando llegue.']);
        }

        try {
            $res = OpsService::reserve($owner, $branch, $customer, [['variant' => $variant->load('product'), 'quantity' => 1]], 0, 'efectivo',
                today()->addDays(self::DAYS), 'Separado desde la página web', 'web');
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['separar' => $e->getMessage()]);
        }
        CustomerTask::create(['customer_id' => $customer->id, 'user_id' => $owner->id, 'created_by' => $owner->id, 'branch_id' => $branch->id,
            'title' => "Confirmar por WhatsApp el pedido web {$res->code}", 'due_at' => now()]);

        $variant->load('attributeValues.attribute');
        $msg = "¡Hola! Separé en la página el {$product->name} ".$variant->label()." (pedido {$res->code}). Soy {$customer->name}.";

        return back()->with('web_order', [
            'code' => $res->code,
            'until' => $res->expires_at->isoFormat('dddd D [de] MMMM'),
            'wa' => whatsapp_link($branch->whatsapp, $msg),
        ]);
    }
}
