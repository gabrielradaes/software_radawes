<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    protected $fillable = [
        'commentable_type', 'commentable_id', 'parent_id', 'user_id', 'customer_id', 'visitor_token',
        'author_name', 'author_phone', 'body', 'is_approved',
    ];

    protected $casts = ['is_approved' => 'boolean'];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->where('is_approved', true)->oldest();
    }

    /** Un comentario escrito por el equipo (admin/empleado) se muestra como respuesta oficial. */
    public function isStaff(): bool
    {
        return $this->user_id !== null;
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
