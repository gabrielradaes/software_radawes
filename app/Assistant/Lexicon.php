<?php

namespace App\Assistant;

use App\Models\AssistantExample;
use App\Models\AssistantWord;
use App\Models\Brand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el equipo le enseñó al asistente desde el panel (sin IA de pago):
 *  - palabras: colores ("preto" = Negro), marcas, clases ("urbano" = Casuales y urbanos) y sinónimos ("depósito" = stock)
 *  - frases de ejemplo de cada acción, para reconocer formas nuevas de pedir lo mismo (parecido de palabras)
 */
class Lexicon
{
    /** Palabras que no dicen nada de la intención (no cuentan para comparar frases). */
    private const STOP = ['que', 'los', 'las', 'del', 'por', 'para', 'con', 'una', 'uno', 'unos', 'unas', 'este', 'esta', 'esto', 'ese', 'esa', 'eso',
        'mi', 'mis', 'tu', 'tus', 'su', 'sus', 'me', 'te', 'se', 'le', 'les', 'nos', 'lo', 'la', 'el', 'de', 'en', 'al', 'a', 'y', 'o', 'es', 'son',
        'quiero', 'quisiera', 'necesito', 'puedes', 'podrias', 'favor', 'porfa', 'porfavor', 'hola', 'bueno', 'ahora', 'aqui', 'ahi', 'pues', 'ya', 'si', 'no'];

    public static function forget(): void
    {
        Cache::forget('assistant:lexicon');
        app()->forgetInstance('assistant.lexicon');
    }

    private static function data(): array
    {
        if (app()->bound('assistant.lexicon')) {
            return app('assistant.lexicon');
        }
        self::$loading = true;
        try {
            $data = self::build();
        } finally {
            self::$loading = false;
        }
        app()->instance('assistant.lexicon', $data);

        return $data;
    }

    private static function build(): array
    {
        return Cache::remember('assistant:lexicon', 600, function () {
            $empty = ['colors' => [], 'brands' => [], 'classes' => [], 'rewrites' => [], 'examples' => []];
            if (! Schema::hasTable('assistant_words')) {
                return $empty;
            }
            $words = AssistantWord::get();
            $brands = Brand::pluck('name')->filter(fn ($n) => mb_strlen($n) >= 3)->mapWithKeys(fn ($n) => [Parser::fold($n) => $n])->all();

            return [
                'colors' => $words->where('kind', 'color')->mapWithKeys(fn ($w) => [$w->phrase => $w->meaning])->all(),
                'brands' => $words->where('kind', 'marca')->mapWithKeys(fn ($w) => [$w->phrase => $w->meaning ?: Parser::title($w->phrase)])->all() + $brands,
                'classes' => $words->where('kind', 'clase')->whereNotNull('category_id')->mapWithKeys(fn ($w) => [$w->phrase => $w->category_id])->all(),
                'rewrites' => $words->where('kind', 'palabra')->filter(fn ($w) => $w->meaning !== null && $w->meaning !== '')->mapWithKeys(fn ($w) => [$w->phrase => $w->meaning])->all(),
                'examples' => AssistantExample::get(['folded', 'intent'])->map(fn ($e) => ['intent' => $e->intent, 'tokens' => self::tokens($e->folded)])
                    ->filter(fn ($e) => $e['tokens'])->values()->all(),
            ];
        });
    }

    /** Palabras con significado de una frase (sin tildes, sin relleno, en singular). */
    public static function tokens(string $folded): array
    {
        // Solo las palabras de la acción: los datos (códigos, números, colores, marcas, tallas) cambian en cada pedido
        static $data = null;
        $data ??= array_flip(array_merge(Parser::COLORS, ['talla', 'color', 'colore', 'marca', 'codigo', 'par', 'pare', 'precio', 'bs', 'boliviano', 'modelo', 'numero', 'preto', 'expreto']));
        $brands = self::$loading ? [] : self::data()['brands'] + self::data()['colors'];
        $out = [];
        foreach (preg_split('/[^a-z0-9]+/', Parser::fold($folded), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (mb_strlen($w) < 3 || preg_match('/\d/', $w) || in_array($w, self::STOP, true) || isset($data[$w]) || isset($brands[$w])) {
                continue;
            }
            $out[Parser::singular($w)] = true;
        }

        return array_values(array_diff(array_keys($out), array_keys($data)));
    }

    /** Mientras se arma la caché no se consulta a sí misma. */
    private static bool $loading = false;

    /** Reemplaza los sinónimos enseñados ("depósito" → "stock") antes de interpretar el mensaje. */
    public static function rewrite(string $text): string
    {
        foreach (self::data()['rewrites'] as $phrase => $meaning) {
            $text = preg_replace('/(?<![\p{L}\d])'.self::accentless($phrase).'(?![\p{L}\d])/iu', $meaning, $text);
        }

        return $text;
    }

    /** Colores enseñados que aparecen en el texto (ya sin tildes). @return array<string, string> frase => color */
    public static function colors(): array
    {
        return self::data()['colors'];
    }

    public static function brandIn(string $folded): ?string
    {
        foreach (self::data()['brands'] as $phrase => $name) {
            if (preg_match('/\b'.preg_quote($phrase, '/').'\b/', $folded)) {
                return $name;
            }
        }

        return null;
    }

    public static function classIn(string $folded): ?int
    {
        foreach (self::data()['classes'] as $phrase => $categoryId) {
            if (preg_match('/\b'.preg_quote($phrase, '/').'\b/', $folded)) {
                return (int) $categoryId;
            }
        }

        return null;
    }

    /**
     * La acción más parecida entre las frases enseñadas.
     * @return array{intent: string, score: float}|null
     */
    public static function classify(string $folded): ?array
    {
        $mine = self::tokens($folded);
        if (! $mine) {
            return null;
        }
        $best = null;
        foreach (self::data()['examples'] as $e) {
            $shared = count(array_intersect($mine, $e['tokens']));
            if (! $shared) {
                continue;
            }
            $score = $shared / sqrt(count($mine) * count($e['tokens']));
            if (! $best || $score > $best['score']) {
                $best = ['intent' => $e['intent'], 'score' => round($score, 3), 'size' => count($mine)];
            }
        }

        return $best;
    }

    /** "deposito" → patrón que también acepta "depósito", "DEPÓSITO"… */
    private static function accentless(string $phrase): string
    {
        $map = ['a' => '[aáàä]', 'e' => '[eéèë]', 'i' => '[iíìï]', 'o' => '[oóòö]', 'u' => '[uúùü]', 'n' => '[nñ]'];
        $out = '';
        foreach (mb_str_split(Parser::fold($phrase)) as $ch) {
            $out .= $map[$ch] ?? ($ch === ' ' ? '\s+' : preg_quote($ch, '/'));
        }

        return $out;
    }
}
