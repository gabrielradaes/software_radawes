<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nota de ingreso: mercadería que llega del proveedor a una tienda. */
class Purchase extends Model
{
    protected $fillable = ['code', 'supplier_id', 'branch_id', 'user_id', 'invoice_number', 'purchased_at', 'pairs', 'total', 'notes'];

    protected $casts = ['purchased_at' => 'date', 'total' => 'decimal:2'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public static function nextCode(Branch $branch): string
    {
        $prefix = 'ING-'.strtoupper(substr(preg_replace('/[^a-z]/i', '', $branch->slug), 0, 2)).'-';
        $last = static::where('code', 'like', $prefix.'%')->orderByDesc('id')->value('code');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
