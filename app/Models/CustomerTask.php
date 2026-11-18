<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Seguimiento con fecha: "llamar a Carlos el viernes". */
class CustomerTask extends Model
{
    protected $fillable = ['customer_id', 'user_id', 'created_by', 'branch_id', 'title', 'due_at', 'done_at'];

    protected $casts = ['due_at' => 'datetime', 'done_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($q)
    {
        return $q->whereNull('done_at');
    }

    /** Tareas que ve un usuario: las suyas; el supervisor además las de su tienda; el admin todas. */
    public function scopeVisibleTo($q, User $user)
    {
        if ($user->isAdmin()) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('user_id', $user->id)
            ->when($user->can('customers.manage') || $user->hasRole(Role::SUPERVISOR), fn ($x) => $x->orWhere('branch_id', $user->branch_id)));
    }

    public function isOverdue(): bool
    {
        return ! $this->done_at && $this->due_at->isPast() && ! $this->due_at->isToday();
    }
}
