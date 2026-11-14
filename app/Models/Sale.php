<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'code', 'branch_id', 'user_id', 'customer_id', 'status', 'channel', 'payment_method',
        'subtotal', 'discount', 'total', 'notes', 'sold_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'sold_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Genera un código secuencial por sucursal: RYE-LP-000001 */
    public static function nextCode(Branch $branch): string
    {
        $prefix = 'RYE-'.strtoupper(substr(preg_replace('/[^a-z]/i', '', $branch->slug), 0, 2)).'-';
        $last = static::where('code', 'like', $prefix.'%')->orderByDesc('id')->value('code');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
