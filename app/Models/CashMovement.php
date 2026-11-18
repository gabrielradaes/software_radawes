<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Entrada (+) o salida (−) de dinero en un turno de caja. */
class CashMovement extends Model
{
    public const TYPES = [
        'venta' => 'Venta', 'anulacion' => 'Anulación', 'adelanto' => 'Adelanto de apartado', 'reembolso' => 'Devolución de dinero',
        'cobro' => 'Cobro por cambio', 'ingreso' => 'Ingreso', 'egreso' => 'Gasto / retiro',
    ];

    protected $fillable = ['cash_session_id', 'user_id', 'type', 'payment_method', 'amount', 'reference_type', 'reference_id', 'note'];

    protected $casts = ['amount' => 'decimal:2'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
