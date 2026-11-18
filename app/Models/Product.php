<?php

namespace App\Models;

use App\Models\Concerns\HasComments;
use App\Models\Concerns\HasLikes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, HasLikes, HasComments;

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'code', 'slug', 'short_description', 'description',
        'base_price', 'compare_at_price', 'currency', 'material', 'origin_country',
        'is_featured', 'is_active', 'published_at',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany(['is_primary' => 'max', 'sort_order' => 'min']);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** Tiendas donde se ofrece este producto. */
    public function branches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot('is_active')->withTimestamps();
    }

    public function scopeInBranch($query, int $branchId)
    {
        return $query->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId)->where('branch_product.is_active', true));
    }

    /** Ficha técnica (ordenada como los campos de la categoría). */
    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class)->with('field')
            ->join('spec_fields', 'spec_fields.id', '=', 'product_specs.spec_field_id')
            ->orderBy('spec_fields.sort_order')->select('product_specs.*');
    }

    public function whatsappLeads(): HasMany
    {
        return $this->hasMany(WhatsappLead::class);
    }

    /** Stock total del producto en una sucursal (suma de variantes). */
    public function stockInBranch(int $branchId): int
    {
        return (int) Stock::whereIn('product_variant_id', $this->variants()->select('id'))
            ->where('branch_id', $branchId)
            ->sum('quantity');
    }

    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->base_price;
    }

    public function discountPercent(): int
    {
        if (! $this->hasDiscount()) {
            return 0;
        }
        return (int) round((1 - $this->base_price / $this->compare_at_price) * 100);
    }

    public function scopePublished($query)
    {
        return $query->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('code', 'like', "%{$term}%")
              ->orWhere('short_description', 'like', "%{$term}%")
              ->orWhere('material', 'like', "%{$term}%");
        });
    }
}
