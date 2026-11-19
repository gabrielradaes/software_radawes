<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    protected $fillable = ['product_variant_id', 'branch_id', 'quantity', 'reserved', 'min_quantity'];

    /** Pares que se pueden vender: los que hay menos los apartados. */
    public function available(): int
    {
        return max(0, (int) $this->quantity - (int) $this->reserved);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    public function isLow(): bool
    {
        return $this->quantity <= $this->min_quantity;
    }

    /**
     * Registra un movimiento y actualiza el saldo. Positivo suma, negativo resta.
     */
    public function move(string $type, int $quantity, ?int $userId = null, ?string $reason = null, ?Model $reference = null): StockMovement
    {
        $this->quantity += $quantity;
        $this->save();

        $movement = $this->movements()->create([
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $this->quantity,
            'reason' => $reason,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
        ]);

        \Illuminate\Support\Facades\Cache::forget("stock:totales:{$this->branch_id}");

        // Si entró mercadería, avisar a los clientes que esperaban esta talla
        if ($quantity > 0) {
            RestockRequest::stockArrived($this);
        }

        return $movement;
    }
}
