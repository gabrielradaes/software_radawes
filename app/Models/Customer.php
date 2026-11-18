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
        'stage', 'source', 'preferred_size', 'branch_id',
    ];

    /** Embudo del CRM, en orden. */
    public const STAGES = [
        'prospecto' => ['Prospecto', 'bg-slate-100 text-slate-700'],
        'interesado' => ['Interesado', 'bg-sky-100 text-sky-800'],
        'cliente' => ['Cliente', 'bg-emerald-100 text-emerald-800'],
        'frecuente' => ['Frecuente', 'bg-amber-100 text-amber-800'],
        'perdido' => ['Perdido', 'bg-rose-100 text-rose-700'],
    ];

    public const SOURCES = ['tienda' => 'En tienda', 'whatsapp' => 'WhatsApp', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'web' => 'Página web', 'referido' => 'Recomendado'];

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage ?? 'prospecto'][0] ?? ucfirst((string) $this->stage);
    }

    public function stageClass(): string
    {
        return self::STAGES[$this->stage ?? 'prospecto'][1] ?? 'bg-slate-100 text-slate-700';
    }

    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class)->latest();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CustomerTask::class)->orderBy('due_at');
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Sube de etapa al comprar (nunca la baja): cliente con 1 compra, frecuente con 3 o más. */
    public function refreshStageAfterSale(): void
    {
        $count = $this->sales()->where('status', '!=', 'anulada')->count();
        $target = $count >= 3 ? 'frecuente' : ($count >= 1 ? 'cliente' : null);
        $order = array_keys(self::STAGES);
        if ($target && ($this->stage === 'perdido' || array_search($target, $order) > array_search($this->stage ?? 'prospecto', $order))) {
            $this->update(['stage' => $target]);
        }
    }

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
