<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use Concerns\Audited;

    protected array $audited = ['name' => 'Nombre', 'phone' => 'Celular', 'is_active' => 'Activo'];

    protected $fillable = ['name', 'contact', 'phone', 'city', 'country', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
