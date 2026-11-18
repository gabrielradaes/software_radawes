<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\User;
use App\Models\WhatsappLead;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Registrar y anular ventas: descuenta/devuelve stock (kardex) y actualiza el CRM del cliente. */
class SaleService
{
    public const PAYMENTS = ['efectivo' => 'Efectivo', 'qr' => 'QR', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia'];

    public const CHANNELS = ['tienda' => 'En tienda', 'whatsapp' => 'WhatsApp', 'web' => 'Web'];

    /**
     * @param  array<int, array{variant: ProductVariant, quantity: int, unit_price?: float|null}>  $lines
     */
    public static function register(User $user, Branch $branch, ?Customer $customer, array $lines, string $payment = 'efectivo',
        string $channel = 'tienda', float $discount = 0, ?string $notes = null): Sale
    {
        if (! $lines) {
            throw new RuntimeException('La venta no tiene productos.');
        }

        return DB::transaction(function () use ($user, $branch, $customer, $lines, $payment, $channel, $discount, $notes) {
            $subtotal = 0;
            $rows = [];
            foreach ($lines as $l) {
                $variant = $l['variant']->loadMissing('product', 'attributeValues.attribute');
                $qty = max(1, (int) $l['quantity']);
                $stock = Stock::where('product_variant_id', $variant->id)->where('branch_id', $branch->id)->lockForUpdate()->first();
                $have = (int) ($stock->quantity ?? 0);
                if ($have < $qty) {
                    throw new RuntimeException("No alcanza el stock de {$variant->product->name} ".($variant->label() ?: '')." en {$branch->name} (hay {$have}).");
                }
                $price = isset($l['unit_price']) && $l['unit_price'] !== null ? (float) $l['unit_price'] : $variant->effectivePrice();
                $subtotal += $price * $qty;
                $rows[] = [$variant, $stock, $qty, $price];
            }
            $discount = min(max(0, $discount), $subtotal);

            $sale = Sale::create([
                'code' => Sale::nextCode($branch),
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'customer_id' => $customer?->id,
                'status' => 'pagada',
                'channel' => array_key_exists($channel, self::CHANNELS) ? $channel : 'tienda',
                'payment_method' => array_key_exists($payment, self::PAYMENTS) ? $payment : 'efectivo',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
                'notes' => $notes,
                'sold_at' => now(),
            ]);

            foreach ($rows as [$variant, $stock, $qty, $price]) {
                $sale->items()->create([
                    'product_variant_id' => $variant->id, 'quantity' => $qty, 'unit_price' => $price, 'unit_cost' => $variant->product->cost,
                    'discount' => 0, 'line_total' => $price * $qty,
                ]);
                $stock->move('venta', -$qty, $user->id, "Venta {$sale->code}", $sale);
            }

            if ($customer) {
                if (! $customer->branch_id) {
                    $customer->update(['branch_id' => $branch->id]);
                }
                $customer->refreshStageAfterSale();
                // Sus consultas por WhatsApp de esos productos quedan como vendidas
                WhatsappLead::where('customer_id', $customer->id)->whereIn('status', ['nuevo', 'contactado'])
                    ->whereIn('product_id', collect($rows)->map(fn ($r) => $r[0]->product_id))
                    ->update(['status' => 'vendido']);
            }

            return $sale;
        });
    }

    public static function cancel(Sale $sale, User $user, ?string $reason = null): void
    {
        if ($sale->status === 'anulada') {
            throw new RuntimeException('Esa venta ya estaba anulada.');
        }
        DB::transaction(function () use ($sale, $user, $reason) {
            foreach ($sale->items()->get() as $item) {
                $stock = Stock::firstOrCreate(['product_variant_id' => $item->product_variant_id, 'branch_id' => $sale->branch_id], ['quantity' => 0, 'min_quantity' => 1]);
                $stock->move('venta', $item->quantity, $user->id, "Anulación de {$sale->code}".($reason ? ": {$reason}" : ''), $sale);
            }
            $sale->update(['status' => 'anulada', 'notes' => trim(($sale->notes ? $sale->notes."\n" : '').'Anulada por '.$user->name.($reason ? ": {$reason}" : ''))]);
        });
    }
}
