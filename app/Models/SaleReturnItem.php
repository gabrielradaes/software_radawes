<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['sale_return_id', 'sale_item_id', 'product_variant_id', 'direction', 'quantity', 'unit_price'];

    protected $casts = ['unit_price' => 'decimal:2'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
