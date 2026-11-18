<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['reservation_id', 'product_variant_id', 'quantity', 'unit_price'];

    protected $casts = ['unit_price' => 'decimal:2'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
