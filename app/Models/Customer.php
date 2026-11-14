<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Cliente: puede registrarse en el sitio público (password) o ser creado por el equipo en una venta (sin password).
 */
class Customer extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'phone', 'email', 'document', 'password', 'city', 'birthdate',
        'is_active', 'last_login_at', 'visitor_token', 'notes',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'birthdate' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class)->latest();
    }

    public function whatsappLeads(): HasMany
    {
        return $this->hasMany(WhatsappLead::class)->latest();
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }

    public function whatsappLink(?string $message = null): string
    {
        return whatsapp_link($this->phone, $message);
    }

    /** Hereda los "me gusta" que dio como visitante anónimo antes de registrarse. */
    public function adoptVisitorLikes(?string $visitorToken): void
    {
        if (! $visitorToken) {
            return;
        }
        $this->update(['visitor_token' => $visitorToken]);

        Like::where('visitor_token', $visitorToken)->whereNull('customer_id')->get()->each(function (Like $like) {
            $dup = Like::where('likeable_type', $like->likeable_type)->where('likeable_id', $like->likeable_id)
                ->where('customer_id', $this->id)->exists();
            $dup ? $like->delete() : $like->update(['customer_id' => $this->id]);
        });
    }
}
