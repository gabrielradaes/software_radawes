<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Turno de caja de una tienda. */
class CashSession extends Model
{
    protected $fillable = ['branch_id', 'opened_by', 'closed_by', 'opened_at', 'opening_amount', 'closed_at',
        'expected_cash', 'counted_cash', 'difference', 'totals', 'notes'];

    protected $casts = [
        'opened_at' => 'datetime', 'closed_at' => 'datetime', 'opening_amount' => 'decimal:2', 'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2', 'difference' => 'decimal:2', 'totals' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->latest('id');
    }

    public static function openFor(int $branchId): ?self
    {
        return static::where('branch_id', $branchId)->whereNull('closed_at')->latest('id')->first();
    }

    /** Dinero neto del turno por forma de pago (ventas, adelantos, cambios, gastos…): ['efectivo' => ['total' => 900.0, 'count' => 2], …] */
    public function totalsByPayment(): array
    {
        return CashMovement::where('cash_session_id', $this->id)
            ->selectRaw("payment_method, SUM(amount) as t, SUM(CASE WHEN type = 'venta' THEN 1 ELSE 0 END) as n")
            ->groupBy('payment_method')->get()->mapWithKeys(fn ($r) => [$r->payment_method => ['total' => (float) $r->t, 'count' => (int) $r->n]])->all();
    }

    public function expectedCash(): float
    {
        return (float) $this->opening_amount + ($this->totalsByPayment()['efectivo']['total'] ?? 0);
    }
}
