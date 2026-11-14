<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'role_id', 'branch_id', 'name', 'email', 'phone', 'avatar_path', 'is_active', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** @var array<string>|null */
    protected ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->role && in_array($this->role->name, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    /** Permisos del rol (el admin los tiene todos vía Gate::before). */
    public function hasPermission(string $permission): bool
    {
        if (! $this->role_id) {
            return false;
        }
        $this->permissionCache ??= $this->role->permissions()->pluck('name')->all();

        return in_array($permission, $this->permissionCache, true);
    }

    /** Sucursal que puede ver: la suya, o cualquiera si es admin global. */
    public function scopedBranchId(): ?int
    {
        return $this->isAdmin() ? null : $this->branch_id;
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }
}
