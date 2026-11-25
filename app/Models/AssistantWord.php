<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Palabra que se le enseñó al asistente: color, marca, clase o sinónimo. */
class AssistantWord extends Model
{
    public const KINDS = [
        'color' => 'Color',
        'marca' => 'Marca',
        'clase' => 'Clase de calzado',
        'palabra' => 'Otra palabra (sinónimo)',
    ];

    protected $fillable = ['kind', 'phrase', 'meaning', 'category_id', 'user_id'];

    protected static function booted(): void
    {
        static::saved(fn () => \App\Assistant\Lexicon::forget());
        static::deleted(fn () => \App\Assistant\Lexicon::forget());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
