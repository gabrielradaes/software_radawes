<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'color'];

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class);
    }

    /** "vip" → etiqueta "VIP"; "mayorista" → "Mayorista" (reutiliza la existente sin importar mayúsculas/tildes). */
    public static function named(string $name): self
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $key = Str::lower(Str::ascii($name));
        $found = static::all()->first(fn ($t) => Str::lower(Str::ascii($t->name)) === $key);
        if ($found) {
            return $found;
        }
        $label = mb_strlen($name) <= 3 ? mb_strtoupper($name) : Str::ucfirst($name);
        $colors = ['amber', 'emerald', 'sky', 'violet', 'rose', 'teal', 'orange', 'slate'];

        return static::create(['name' => mb_substr($label, 0, 40), 'color' => $colors[static::count() % count($colors)]]);
    }
}
