<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cambio de talla/modelo o devolución sobre una venta. */
class SaleReturn extends Model
{
    protected $fillable = ['sale_id', 'user_id', 'branch_id', 'type', 'returned_value', 'given_value', 'balance', 'payment_method', 'reason'];

    protected $casts = ['returned_value' => 'decimal:2', 'given_value' => 'decimal:2', 'balance' => 'decimal:2'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
