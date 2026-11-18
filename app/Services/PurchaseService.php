<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Notas de ingreso: suben el stock (kardex tipo "compra") y actualizan el costo del producto. */
class PurchaseService
{
    /**
     * @param  array<int, array{product: Product, color: ?string, sizes: array<string,int>, quantity?: int, unit_cost: float}>  $blocks
     *         Cada bloque = un producto en un color, con pares por talla (o "quantity" si no tiene tallas).
     */
    public static function register(User $user, Branch $branch, ?Supplier $supplier, array $blocks, ?string $invoice = null, ?string $date = null, ?string $notes = null): Purchase
    {
        return DB::transaction(function () use ($user, $branch, $supplier, $blocks, $invoice, $date, $notes) {
            $purchase = Purchase::create([
                'code' => Purchase::nextCode($branch), 'supplier_id' => $supplier?->id, 'branch_id' => $branch->id, 'user_id' => $user->id,
                'invoice_number' => $invoice, 'purchased_at' => $date ?? now()->toDateString(), 'notes' => $notes,
            ]);
            $pairs = 0;
            $total = 0;

            foreach ($blocks as $b) {
                /** @var Product $product */
                $product = $b['product'];
                $product->branches()->syncWithoutDetaching([$branch->id => ['is_active' => true]]);
                $colorIds = $b['color'] ? [VariantBuilder::value('color', $b['color'])->id] : [];
                $sizes = array_filter($b['sizes'] ?? [], fn ($q) => (int) $q > 0);
                $cost = (float) $b['unit_cost'];

                $lines = [];
                if ($sizes) {
                    foreach ($sizes as $size => $qty) {
                        $variant = VariantBuilder::generate($product, $colorIds, [VariantBuilder::value('talla', (string) $size)->id])->first();
                        $lines[] = [$variant, (int) $qty];
                    }
                } elseif (($b['quantity'] ?? 0) > 0) {
                    $variant = $colorIds ? VariantBuilder::generate($product, $colorIds, [])->first() : ($product->variants()->first() ?? $product->variants()->create(['sku' => 'P'.str_pad((string) $product->id, 3, '0', STR_PAD_LEFT)]));
                    $lines[] = [$variant, (int) $b['quantity']];
                }

                foreach ($lines as [$variant, $qty]) {
                    $purchase->items()->create(['product_variant_id' => $variant->id, 'quantity' => $qty, 'unit_cost' => $cost, 'line_total' => $cost * $qty]);
                    Stock::firstOrCreate(['product_variant_id' => $variant->id, 'branch_id' => $branch->id], ['quantity' => 0, 'min_quantity' => 1])
                        ->move('compra', $qty, $user->id, "Ingreso {$purchase->code}".($supplier ? " · {$supplier->name}" : ''), $purchase);
                    $pairs += $qty;
                    $total += $cost * $qty;
                }
                if ($cost > 0) {
                    $product->update(['cost' => $cost]);   // último costo
                }
            }

            if (! $pairs) {
                throw new RuntimeException('La compra no tiene pares: escribe cuántos llegaron de cada talla.');
            }
            $purchase->update(['pairs' => $pairs, 'total' => $total]);

            return $purchase;
        });
    }
}
