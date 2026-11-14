<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'price', 'is_active'];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product_variant')
            ->with('attribute');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Precio efectivo: el de la variante o, si no tiene, el del producto. */
    public function effectivePrice(): float
    {
        return (float) ($this->price ?? $this->product->base_price);
    }

    /** Ej: "Talla 42 · Café" */
    public function label(): string
    {
        return $this->attributeValues
            ->map(fn ($v) => $v->attribute->name === 'Talla' ? "Talla {$v->value}" : $v->value)
            ->implode(' · ');
    }

    public function valueOf(string $attributeSlug): ?string
    {
        return $this->attributeValues->firstWhere('attribute.slug', $attributeSlug)?->value;
    }

    public function stockInBranch(int $branchId): int
    {
        return (int) $this->stocks()->where('branch_id', $branchId)->value('quantity');
    }
}
