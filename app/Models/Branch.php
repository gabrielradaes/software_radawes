<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'name', 'slug', 'domain', 'brand_name', 'brand_tagline', 'brand_small', 'tagline', 'description', 'city', 'country', 'address',
        'phone', 'whatsapp', 'email', 'opening_hours', 'latitude', 'longitude',
        'logo_path', 'cover_path', 'color_primary', 'color_accent', 'color_surface', 'is_main', 'is_active',
    ];

    /** Productos que esta tienda ofrece (catálogo propio). */
    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('is_active')->withTimestamps();
    }

    public function brandName(): string
    {
        return $this->brand_name ?: $this->name;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(SocialLink::class)->orderBy('sort_order');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function whatsappLeads(): HasMany
    {
        return $this->hasMany(WhatsappLead::class);
    }

    /** Enlace wa.me con mensaje prellenado. */
    public function whatsappLink(?string $message = null): string
    {
        return whatsapp_link($this->whatsapp, $message);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
