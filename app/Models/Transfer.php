<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Traspaso entre tiendas en 2 pasos: la tienda de origen envía y la de destino confirma que recibió. */
class Transfer extends Model
{
    protected $fillable = ['code', 'from_branch_id', 'to_branch_id', 'sent_by', 'received_by', 'status', 'pairs', 'notes', 'received_at'];

    protected $casts = ['received_at' => 'datetime'];

    public function from(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    public static function nextCode(): string
    {
        $last = static::orderByDesc('id')->value('code');

        return 'TR-'.str_pad((string) (($last ? (int) substr($last, 3) : 0) + 1), 6, '0', STR_PAD_LEFT);
    }
}
