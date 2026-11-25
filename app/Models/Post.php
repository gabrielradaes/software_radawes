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
        'user_id', 'branch_id', 'product_id', 'type', 'title', 'body', 'bg', 'text_color', 'is_pinned', 'published_at',
    ];

    /** Colores listos para elegir (además de cualquier color propio #rrggbb). */
    public const SOLIDS = [
        '#111827' => 'Negro', '#78350f' => 'Cuero', '#7f1d1d' => 'Vino', '#b91c1c' => 'Rojo', '#ea580c' => 'Naranja',
        '#ca8a04' => 'Mostaza', '#15803d' => 'Verde', '#0f766e' => 'Turquesa', '#1d4ed8' => 'Azul', '#6d28d9' => 'Morado',
        '#db2777' => 'Rosa', '#f5f5f4' => 'Hueso',
    ];

    public const GRADIENTS = [
        'g-atardecer' => ['Atardecer', '#f97316', '#db2777'],
        'g-fiesta' => ['Fiesta', '#db2777', '#7c3aed'],
        'g-oceano' => ['Océano', '#0ea5e9', '#4338ca'],
        'g-bosque' => ['Bosque', '#16a34a', '#0f766e'],
        'g-cuero' => ['Cuero', '#92400e', '#451a03'],
        'g-oro' => ['Oro', '#eab308', '#a16207'],
        'g-noche' => ['Noche', '#111827', '#4c1d95'],
        'g-pasion' => ['Pasión', '#dc2626', '#7f1d1d'],
    ];

    /** Valida un fondo: color #rrggbb o un degradado de la lista (nunca CSS libre). */
    public static function validBg(?string $bg): bool
    {
        return $bg === null || $bg === '' || (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $bg) || isset(self::GRADIENTS[$bg]);
    }

    /** null si la publicación es normal (blanca); si no, ['bg' => css, 'fg' => color de letra, 'soft' => color suave]. */
    public function colorStyle(): ?array
    {
        $bg = $this->bg;
        if (! $bg || ! self::validBg($bg) || strtolower($bg) === '#ffffff') {
            return null;
        }
        if (isset(self::GRADIENTS[$bg])) {
            [, $a, $b] = self::GRADIENTS[$bg];
            $css = "linear-gradient(135deg, {$a}, {$b})";
            $lum = (self::luminance($a) + self::luminance($b)) / 2;
        } else {
            $css = $bg;
            $lum = self::luminance($bg);
        }
        $fg = $this->text_color && preg_match('/^#[0-9a-fA-F]{6}$/', $this->text_color) ? $this->text_color : ($lum > 0.179 ? '#111827' : '#ffffff');

        return ['bg' => $css, 'fg' => $fg, 'soft' => self::luminance($fg) > 0.5 ? 'rgba(255,255,255,.18)' : 'rgba(0,0,0,.08)'];
    }

    public static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split($hex, 2));
        $lin = fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    }

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
