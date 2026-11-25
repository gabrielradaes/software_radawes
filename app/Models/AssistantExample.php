<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Frase de ejemplo de una acción del asistente ("cárgame esto al depósito" = entrada). */
class AssistantExample extends Model
{
    protected $fillable = ['text', 'folded', 'intent', 'source', 'user_id'];

    protected static function booted(): void
    {
        static::saving(fn (self $e) => $e->folded = mb_substr(\App\Assistant\Parser::fold(trim($e->text)), 0, 300));
        static::saved(fn () => \App\Assistant\Lexicon::forget());
        static::deleted(fn () => \App\Assistant\Lexicon::forget());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
