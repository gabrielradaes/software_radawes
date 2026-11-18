<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Inventario físico de una tienda: se cuenta escaneando y al cerrar se ajustan las diferencias. */
class StockCount extends Model
{
    protected $fillable = ['branch_id', 'user_id', 'closed_by', 'scope', 'status', 'notes', 'closed_at'];

    protected $casts = ['closed_at' => 'datetime'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }
}
