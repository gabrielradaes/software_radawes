<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Curva de tallas: cuántos pares de cada talla vienen por color (ej. 38-1, 39-2, 40-3, 41-3, 42-2, 43-1). */
class SizeCurve extends Model
{
    protected $fillable = ['name'];

    public function items(): HasMany
    {
        return $this->hasMany(SizeCurveItem::class)->orderByRaw('CAST(size AS UNSIGNED)');
    }

    /** "38-1 · 39-2 · 40-3" */
    public function summary(): string
    {
        return $this->items->map(fn ($i) => "{$i->size}·{$i->quantity}")->implode('  ');
    }

    public function pairs(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
