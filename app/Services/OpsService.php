<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\CustomerTask;
use App\Models\ProductVariant;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Stock;
use App\Models\StockCount;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Operación diaria: caja, cambios/devoluciones, apartados, inventario físico y traspasos. */
class OpsService
{
    // =================================================================== caja

    /** Registra dinero en el turno abierto de la tienda (si no hay caja abierta, no hace nada). */
    public static function cash(int $branchId, string $type, string $method, float $amount, ?User $user, ?Model $ref = null, ?string $note = null): ?CashMovement
    {
        if (abs($amount) < 0.005 || ! ($session = CashSession::openFor($branchId))) {
            return null;
        }

        return $session->movements()->create([
            'user_id' => $user?->id, 'type' => $type, 'payment_method' => $method, 'amount' => round($amount, 2),
            'reference_type' => $ref ? $ref::class : null, 'reference_id' => $ref?->getKey(), 'note' => $note,
        ]);
    }

    private static function stockFor(int $variantId, int $branchId, bool $lock = true): Stock
    {
        $q = Stock::where('product_variant_id', $variantId)->where('branch_id', $branchId);

        return ($lock ? $q->lockForUpdate()->first() : $q->first())
            ?? Stock::create(['product_variant_id' => $variantId, 'branch_id' => $branchId, 'quantity' => 0, 'min_quantity' => 1]);
    }

    private static function label(ProductVariant $v): string
    {
        $v->loadMissing('product', 'attributeValues.attribute');

        return trim($v->product->name.' '.$v->label());
    }

    // =================================================================== cambios y devoluciones

    /**
     * @param  array<int, int>  $returns  sale_item_id => pares que vuelven
     * @param  array<int, array{variant: ProductVariant, quantity: int}>  $gives  pares que se lleva a cambio
     */
    public static function exchange(Sale $sale, User $user, array $returns, array $gives = [], string $method = 'efectivo', ?string $reason = null): SaleReturn
    {
        if ($sale->status === 'anulada') {
            throw new RuntimeException('Esa venta está anulada.');
        }

        return DB::transaction(function () use ($sale, $user, $returns, $gives, $method, $reason) {
            $sale->load('items.variant');
            $already = SaleReturnItem::whereIn('sale_item_id', $sale->items->pluck('id'))->where('direction', 'in')
                ->groupBy('sale_item_id')->selectRaw('sale_item_id, SUM(quantity) as q')->pluck('q', 'sale_item_id');

            $ret = SaleReturn::create(['sale_id' => $sale->id, 'user_id' => $user->id, 'branch_id' => $sale->branch_id,
                'type' => $gives ? 'cambio' : 'devolucion', 'payment_method' => $method, 'reason' => $reason]);
            $in = 0;
            $out = 0;
            $text = [];

            foreach ($returns as $itemId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }
                $item = $sale->items->firstWhere('id', (int) $itemId) ?? throw new RuntimeException('Ese producto no es de esta venta.');
                $left = $item->quantity - (int) ($already[$item->id] ?? 0);
                if ($qty > $left) {
                    throw new RuntimeException('De '.self::label($item->variant)." solo se pueden devolver {$left}.");
                }
                $ret->items()->create(['sale_item_id' => $item->id, 'product_variant_id' => $item->product_variant_id, 'direction' => 'in', 'quantity' => $qty, 'unit_price' => $item->unit_price]);
                self::stockFor($item->product_variant_id, $sale->branch_id)->move('devolucion', $qty, $user->id, "Devolución de {$sale->code}", $sale);
                $in += $qty * (float) $item->unit_price;
                $text[] = "devolvió {$qty}× ".self::label($item->variant);
            }
            if (! $ret->items()->exists()) {
                throw new RuntimeException('Elige qué pares devuelve el cliente.');
            }

            foreach ($gives as $g) {
                $qty = (int) $g['quantity'];
                $stock = self::stockFor($g['variant']->id, $sale->branch_id);
                if ($stock->available() < $qty) {
                    throw new RuntimeException('No hay '.self::label($g['variant'])." (disponibles: {$stock->available()}).");
                }
                $price = $g['variant']->effectivePrice();
                $ret->items()->create(['product_variant_id' => $g['variant']->id, 'direction' => 'out', 'quantity' => $qty, 'unit_price' => $price]);
                $stock->move('cambio', -$qty, $user->id, "Cambio en {$sale->code}", $sale);
                $out += $qty * $price;
                $text[] = "se llevó {$qty}× ".self::label($g['variant']);
            }

            $balance = $out - $in;
            $ret->update(['returned_value' => $in, 'given_value' => $out, 'balance' => $balance]);
            if ($balance > 0) {
                self::cash($sale->branch_id, 'cobro', $method, $balance, $user, $ret, "Diferencia por cambio en {$sale->code}");
            } elseif ($balance < 0) {
                self::cash($sale->branch_id, 'reembolso', $method, $balance, $user, $ret, "Devolución de dinero de {$sale->code}");
            }
            $sale->customer?->interactions()->create(['user_id' => $user->id, 'type' => 'visita',
                'body' => '🔁 '.ucfirst($ret->type)." en {$sale->code}: ".implode('; ', $text).($reason ? " ({$reason})" : '')]);

            return $ret;
        });
    }

    // =================================================================== apartados

    /** @param array<int, array{variant: ProductVariant, quantity: int, unit_price?: ?float}> $lines */
    public static function reserve(User $user, Branch $branch, Customer $customer, array $lines, float $deposit, string $method, Carbon|string $expires, ?string $notes = null): Reservation
    {
        if (! $lines) {
            throw new RuntimeException('Agrega al menos un par.');
        }

        return DB::transaction(function () use ($user, $branch, $customer, $lines, $deposit, $method, $expires, $notes) {
            $res = Reservation::create(['code' => Reservation::nextCode($branch), 'branch_id' => $branch->id, 'customer_id' => $customer->id,
                'user_id' => $user->id, 'total' => 0, 'deposit' => $deposit, 'deposit_method' => $method, 'expires_at' => Carbon::parse($expires), 'notes' => $notes]);
            $total = 0;
            foreach ($lines as $l) {
                $qty = max(1, (int) $l['quantity']);
                $stock = self::stockFor($l['variant']->id, $branch->id);
                if ($stock->available() < $qty) {
                    throw new RuntimeException('No hay '.self::label($l['variant'])." disponible para apartar (hay {$stock->available()}).");
                }
                $stock->increment('reserved', $qty);
                $price = isset($l['unit_price']) && $l['unit_price'] !== null ? (float) $l['unit_price'] : $l['variant']->effectivePrice();
                $res->items()->create(['product_variant_id' => $l['variant']->id, 'quantity' => $qty, 'unit_price' => $price]);
                $total += $price * $qty;
            }
            if ($deposit > $total) {
                throw new RuntimeException('El adelanto no puede ser mayor que el total ('.money($total).').');
            }
            $res->update(['total' => $total]);
            self::cash($branch->id, 'adelanto', $method, $deposit, $user, $res, "Adelanto {$res->code} · {$customer->name}");

            // Recordatorio en el CRM un día antes de que venza
            $due = $res->expires_at->copy()->subDay()->setTime(10, 0);
            CustomerTask::create(['customer_id' => $customer->id, 'user_id' => $user->id, 'created_by' => $user->id, 'branch_id' => $branch->id,
                'title' => "Recordar que retire su apartado {$res->code} (saldo ".money($total - $deposit).')', 'due_at' => $due->isPast() ? now()->addHour() : $due]);
            $customer->interactions()->create(['user_id' => $user->id, 'type' => 'visita', 'body' => "📌 Apartó {$res->code} por ".money($total).' con adelanto de '.money($deposit)]);
            if ($customer->stage === 'prospecto') {
                $customer->update(['stage' => 'interesado']);
            }

            return $res;
        });
    }

    private static function releaseReservation(Reservation $res): void
    {
        foreach ($res->items as $it) {
            $stock = self::stockFor($it->product_variant_id, $res->branch_id);
            $stock->update(['reserved' => max(0, $stock->reserved - $it->quantity)]);
        }
        CustomerTask::where('customer_id', $res->customer_id)->whereNull('done_at')->where('title', 'like', "%{$res->code}%")->update(['done_at' => now()]);
    }

    /** El cliente retira: se libera la reserva y se registra la venta cobrando solo el saldo. */
    public static function pickUp(Reservation $res, User $user, string $method = 'efectivo'): Sale
    {
        if ($res->status !== 'activo') {
            throw new RuntimeException('Este apartado ya no está activo.');
        }

        return DB::transaction(function () use ($res, $user, $method) {
            $res->load('items.variant', 'branch', 'customer');
            self::releaseReservation($res);
            $sale = SaleService::register($user, $res->branch, $res->customer,
                $res->items->map(fn ($i) => ['variant' => $i->variant, 'quantity' => $i->quantity, 'unit_price' => (float) $i->unit_price])->all(),
                $method, 'tienda', 0, "Apartado {$res->code} (adelanto ".money($res->deposit).')', (float) $res->deposit);
            $res->update(['status' => 'retirado', 'sale_id' => $sale->id]);

            return $sale;
        });
    }

    public static function cancelReservation(Reservation $res, User $user, bool $refundDeposit): void
    {
        if ($res->status !== 'activo') {
            throw new RuntimeException('Este apartado ya no está activo.');
        }
        DB::transaction(function () use ($res, $user, $refundDeposit) {
            $res->load('items', 'customer');
            self::releaseReservation($res);
            if ($refundDeposit) {
                self::cash($res->branch_id, 'reembolso', $res->deposit_method, -(float) $res->deposit, $user, $res, "Devolución de adelanto {$res->code}");
            }
            $res->update(['status' => 'cancelado', 'notes' => trim(($res->notes ? $res->notes."\n" : '').'Cancelado por '.$user->name.($refundDeposit ? ' (se devolvió el adelanto)' : ' (adelanto retenido)'))]);
            $res->customer->interactions()->create(['user_id' => $user->id, 'type' => 'nota', 'body' => "✖ Se canceló el apartado {$res->code}".($refundDeposit ? ' y se devolvió el adelanto.' : '.')]);
        });
    }

    // =================================================================== inventario físico

    /** Filas del conteo: [variant, sistema, contado, diferencia] */
    public static function countRows(StockCount $count): Collection
    {
        $count->load('items.variant.product', 'items.variant.attributeValues.attribute');
        $items = $count->items->keyBy('product_variant_id');
        $variantIds = $items->keys();
        if ($count->scope === 'total') {
            $variantIds = $variantIds->merge(Stock::where('branch_id', $count->branch_id)->where('quantity', '!=', 0)->pluck('product_variant_id'))->unique();
        }
        $system = Stock::where('branch_id', $count->branch_id)->whereIn('product_variant_id', $variantIds)->pluck('quantity', 'product_variant_id');
        $variants = ProductVariant::with('product', 'attributeValues.attribute')->whereIn('id', $variantIds)->get()->keyBy('id');

        return $variantIds->map(function ($id) use ($items, $system, $variants, $count) {
            $sys = $count->status === 'cerrado' ? (int) ($items[$id]->system_qty ?? 0) : (int) ($system[$id] ?? 0);
            $counted = (int) ($items[$id]->counted ?? 0);

            return ['variant' => $variants[$id], 'system' => $sys, 'counted' => $counted, 'diff' => $counted - $sys];
        })->sortBy(fn ($r) => $r['variant']->product->name.'|'.$r['variant']->label())->values();
    }

    public static function closeCount(StockCount $count, User $user): void
    {
        if ($count->status !== 'abierto') {
            throw new RuntimeException('Este inventario ya está cerrado.');
        }
        DB::transaction(function () use ($count, $user) {
            foreach (self::countRows($count) as $r) {
                $item = $count->items()->firstOrCreate(['product_variant_id' => $r['variant']->id], ['counted' => 0]);
                $item->update(['system_qty' => $r['system'], 'difference' => $r['diff']]);
                if ($r['diff'] !== 0) {
                    self::stockFor($r['variant']->id, $count->branch_id)->move('inventario', $r['diff'], $user->id, "Inventario físico #{$count->id}", $count);
                }
            }
            $count->update(['status' => 'cerrado', 'closed_by' => $user->id, 'closed_at' => now()]);
        });
    }

    // =================================================================== traspasos

    /** @param array<int, array{variant: ProductVariant, quantity: int}> $lines */
    public static function sendTransfer(User $user, Branch $from, Branch $to, array $lines, ?string $notes = null): Transfer
    {
        if ($from->id === $to->id) {
            throw new RuntimeException('El origen y el destino son la misma tienda.');
        }
        if (! $lines) {
            throw new RuntimeException('Agrega al menos un par.');
        }

        return DB::transaction(function () use ($user, $from, $to, $lines, $notes) {
            $tr = Transfer::create(['code' => Transfer::nextCode(), 'from_branch_id' => $from->id, 'to_branch_id' => $to->id, 'sent_by' => $user->id, 'notes' => $notes]);
            $pairs = 0;
            foreach ($lines as $l) {
                $qty = max(1, (int) $l['quantity']);
                $stock = self::stockFor($l['variant']->id, $from->id);
                if ($stock->available() < $qty) {
                    throw new RuntimeException('No alcanza '.self::label($l['variant'])." en {$from->name} (disponibles: {$stock->available()}).");
                }
                $stock->move('traspaso', -$qty, $user->id, "Enviado {$tr->code} a {$to->name}", $tr);
                $tr->items()->create(['product_variant_id' => $l['variant']->id, 'quantity' => $qty]);
                $l['variant']->product->branches()->syncWithoutDetaching([$to->id => ['is_active' => true]]);
                $pairs += $qty;
            }
            $tr->update(['pairs' => $pairs]);

            return $tr;
        });
    }

    public static function receiveTransfer(Transfer $tr, User $user): void
    {
        if ($tr->status !== 'enviado') {
            throw new RuntimeException('Este traspaso ya no está en camino.');
        }
        DB::transaction(function () use ($tr, $user) {
            $tr->load('items', 'from');
            foreach ($tr->items as $it) {
                self::stockFor($it->product_variant_id, $tr->to_branch_id)->move('traspaso', $it->quantity, $user->id, "Recibido {$tr->code} de {$tr->from->name}", $tr);
            }
            $tr->update(['status' => 'recibido', 'received_by' => $user->id, 'received_at' => now()]);
        });
    }

    public static function cancelTransfer(Transfer $tr, User $user): void
    {
        if ($tr->status !== 'enviado') {
            throw new RuntimeException('Solo se puede cancelar un traspaso que está en camino.');
        }
        DB::transaction(function () use ($tr, $user) {
            $tr->load('items');
            foreach ($tr->items as $it) {
                self::stockFor($it->product_variant_id, $tr->from_branch_id)->move('traspaso', $it->quantity, $user->id, "Cancelado {$tr->code}: vuelve a la tienda", $tr);
            }
            $tr->update(['status' => 'cancelado']);
        });
    }
}
