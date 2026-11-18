<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use Concerns\Audited;

    protected array $audited = ['name' => 'Nombre', 'is_active' => 'Activa', 'parent_id' => 'Dentro de'];

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'icon', 'image_path', 'sort_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Campos de ficha técnica: se definen en la categoría principal. */
    public function specFields(): HasMany
    {
        return $this->hasMany(SpecField::class)->orderBy('sort_order');
    }

    /** La categoría principal (raíz) de esta categoría. */
    public function root(): Category
    {
        return $this->parent_id ? $this->parent->root() : $this;
    }

    /** IDs de esta categoría y todas sus hijas (para filtrar el catálogo). */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }
        return $ids;
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
