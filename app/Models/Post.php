<?php

namespace App\Models;

use App\Models\Concerns\HasComments;
use App\Models\Concerns\HasLikes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes, HasLikes, HasComments, Concerns\Audited;

    protected bool $auditCreated = false;

    protected array $audited = ['title' => 'Título', 'published_at' => 'Publicado', 'branch_id' => 'Tienda'];

    protected $fillable = [
        'user_id', 'branch_id', 'product_id', 'type', 'title', 'body', 'is_pinned', 'published_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** Posts globales + los de la sucursal indicada. */
    public function scopeForBranch($query, ?int $branchId)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->whereNull('branch_id');
            if ($branchId) {
                $q->orWhere('branch_id', $branchId);
            }
        });
    }
}
