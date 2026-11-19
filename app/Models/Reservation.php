<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Apartado: el cliente deja un adelanto y los pares quedan reservados hasta que los retire. */
class Reservation extends Model
{
    protected $fillable = ['code', 'branch_id', 'customer_id', 'user_id', 'sale_id', 'status', 'source', 'total', 'deposit', 'deposit_method', 'expires_at', 'notes'];

    protected $casts = ['total' => 'decimal:2', 'deposit' => 'decimal:2', 'expires_at' => 'date'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function balance(): float
    {
        return max(0, (float) $this->total - (float) $this->deposit);
    }

    public function isExpired(): bool
    {
        return $this->status === 'activo' && $this->expires_at->lt(today());
    }

    public static function nextCode(Branch $branch): string
    {
        $prefix = 'AP-'.strtoupper(substr(preg_replace('/[^a-z]/i', '', $branch->slug), 0, 2)).'-';
        $last = static::where('code', 'like', $prefix.'%')->orderByDesc('id')->value('code');

        return $prefix.str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 6, '0', STR_PAD_LEFT);
    }
}
