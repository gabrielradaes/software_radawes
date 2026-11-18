<?php

namespace App\Assistant;

use App\Models\AttributeValue;
use App\Models\Branch;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Entiende órdenes habladas o escritas del negocio (sin IA de pago):
 * intención + datos (código, tallas, colores, precio, cantidad, tienda, clase, material, nombre…).
 *
 * Trabaja sobre dos versiones del texto con la MISMA longitud en caracteres:
 *   $t = texto original (con mayúsculas y tildes, números ya en cifras)
 *   $a = versión en minúsculas y sin tildes, para buscar palabras clave
 */
class Parser
{
    public const COLORS = [
        'azul marino', 'verde oliva', 'negro', 'cafe', 'marron', 'miel', 'blanco', 'azul', 'rojo', 'beige', 'gris', 'plomo',
        'guinda', 'vino', 'camel', 'tan', 'cognac', 'chocolate', 'verde', 'mostaza', 'crema', 'hueso', 'nude', 'tabaco',
        'caramelo', 'natural', 'dorado', 'plateado', 'rosado', 'celeste', 'amarillo', 'naranja', 'morado', 'lila', 'habano', 'arena',
    ];

    /** Palabras que cortan un nombre o un código. */
    private const STOP = 'codigo|cod|color|colores|talla|tallas|precio|cuesta|vale|en|para|de|del|y|con|tienda|sucursal|cantidad|material|clase|categoria|a|al|que|los|las|el|la|por|cada|pares|par|bs|bolivianos|publica|publicalo|publicar|carrusel|separadas';

    public string $t;

    public string $a;

    public function __construct(public string $original)
    {
        $this->t = trim(preg_replace('/\s+/u', ' ', Numbers::toDigits($original)));
        $this->a = self::fold($this->t);
    }

    /** Minúsculas y sin tildes, misma cantidad de caracteres. */
    public static function fold(string $s): string
    {
        return strtr(mb_strtolower($s), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    // ---------------------------------------------------------------- intención

    public function intent(): ?string
    {
        $a = $this->a;

        return match (true) {
            (bool) preg_match('/\b(ayuda|que puedes hacer|que sabes hacer|como funciona|como te uso|ejemplos?|comandos)\b/', $a) => 'ayuda',
            (bool) preg_match('/\b(nuevo|nueva|registra(?:r|lo)?|crea(?:r|lo)?|da(?:r)? de alta|alta de)\b/', $a) => 'nuevo_producto',
            (bool) preg_match('/\b(salida|vendi|vendio|vendimos|vendieron|vendido|se llevo|se llevaron|retira(?:r|ron)?|saca(?:r|ron)?|baja de)\b/', $a) => 'salida',
            (bool) preg_match('/\b(entrada|ingreso|ingresaron|ingresa(?:r)?|llego|llegaron|recibimos|recibi|repon(?:er|go)?|suma(?:r)?|agrega(?:r)?)\b/', $a) => 'entrada',
            (bool) preg_match('/\b(publica(?:r|lo|la)?|publicacion|postea(?:r|lo)?|sube(?:lo)? a (?:novedades|la pagina))\b/', $a) => 'publicar',
            (bool) preg_match('/\b(cuant[oa]s?|hay|queda[n]?|tienes|tenemos|stock|existencias?|disponibles?|busca(?:r)?|consulta(?:r)?|ver)\b|\?/', $a) => 'consulta',
            default => null,
        };
    }

    public function wantsPublish(): bool
    {
        return (bool) preg_match('/\b(publica(?:r|lo|la)?|y (?:lo )?(?:sube|subes)|postea(?:r|lo)?)\b/', $this->a);
    }

    public function isYes(): bool
    {
        return (bool) preg_match('/^\W*(si|ok|okey|dale|confirmo|confirmar|confirmado|correcto|de acuerdo|listo|hazlo|guarda(?:lo)?|registra(?:lo)?|adelante|perfecto|esta bien)\b/', $this->a);
    }

    public function isNo(): bool
    {
        return (bool) preg_match('/^\W*(no|cancela(?:r|lo)?|anula(?:r|lo)?|olvidalo|deja(?:lo)?|nada)\W*$/', $this->a)
            || (bool) preg_match('/^\W*(cancela(?:r|lo)?|anula(?:r|lo)?|olvidalo)\b/', $this->a);
    }

    public function says(string $regex): bool
    {
        return (bool) preg_match($regex, $this->a);
    }

    // ---------------------------------------------------------------- datos

    /** Código del modelo: "código CH 210" → CH-210. También reconoce "CH-210" suelto. */
    public function code(): ?string
    {
        if (preg_match('/\bsin codigo\b/', $this->a)) {
            return '';
        }
        if (preg_match('/\b(?:codigo|cod|modelo)\s*(?:es|del producto|numero|nro|n)?\s*[:#.]?\s*((?:[a-z0-9][a-z0-9\-\/.]*\s?){1,3})/u', $this->a, $m, PREG_OFFSET_CAPTURE)) {
            $tokens = [];
            foreach (preg_split('/\s+/', trim($m[1][0])) as $tok) {
                if (preg_match('/^('.self::STOP.')$/', $tok)) {
                    break;
                }
                $tokens[] = $tok;
            }
            $code = self::normalizeCode(implode(' ', $tokens));
            if ($code !== '' && preg_match('/\d/', $code)) {
                return $code;
            }
        }
        // Suelto: "CH-210" o "CH210" (sin espacio), "CH 210" en mayúsculas, o "del ch 210"
        $notCode = '/^(bs|es|son|el|la|los|las|de|del|al|a|y|e|en|un|una|con|por|hay|me|mi|tu|su|lo|le|que|mas|sin|cada|unos|unas|par|pares|talla|tallas|precio|total|solo)$/';
        foreach ([
            ['/\b([a-z]{1,4})-?(\d{2,6}[a-z]?)\b(?!\s*(?:pares|par|bs|bolivianos))/', $this->a],
            ['/\b([A-Z]{1,4}) (\d{2,6}[A-Z]?)\b/', Str::ascii($this->t)],
            ['/\b(?:del|el|al)\s+([a-z]{1,4})\s(\d{2,6}[a-z]?)\b(?!\s*(?:pares|par|bs|bolivianos))/', $this->a],
        ] as [$re, $text]) {
            if (preg_match_all($re, $text, $all, PREG_SET_ORDER)) {
                foreach ($all as $m) {
                    if (! preg_match($notCode, strtolower($m[1]))) {
                        return self::normalizeCode($m[1].'-'.$m[2]);
                    }
                }
            }
        }

        return null;
    }

    /** Respuesta corta a "¿cuál es el código?": "es el CH 210" → CH-210 */
    public function bareCode(): string
    {
        $s = preg_replace('/^(?:(?:el|es|su|codigo|cod|modelo|numero)\s+)+/', '', trim($this->a, ' .,'));

        return self::normalizeCode(implode(' ', array_slice(preg_split('/\s+/', $s), 0, 3)));
    }

    public static function normalizeCode(string $code): string
    {
        $code = strtoupper(trim(Str::ascii($code), " .-/"));
        $code = preg_replace('/\s+/', '-', $code);

        return preg_replace('/-+/', '-', $code);
    }

    /** Tallas: "tallas 38 al 42", "del 38 al 42", "talla 40", "tallas 38, 39 y 41". */
    public function tallas(): array
    {
        $a = $this->a;
        if (preg_match('/\b(?:tallas?\s*)?(?:de(?:l|sde)?\s*(?:la\s*)?)?(\d{2})\s*(?:al?|hasta|-)\s*(?:la\s*)?(\d{2})\b/', $a, $m)
            && preg_match('/\btallas?\b|\bdel?\s*\d{2}\s*al?\s*\d{2}/', $a)) {
            [$from, $to] = [(int) $m[1], (int) $m[2]];
            if ($from >= 15 && $to <= 50 && $from <= $to && $to - $from <= 20) {
                return array_map('strval', range($from, $to));
            }
        }
        if (preg_match('/\btallas?\s*((?:\d{2}(?:\s*(?:,|y|e)\s*)?)+)/', $a, $m)) {
            preg_match_all('/\d{2}/', $m[1], $nums);

            return collect($nums[0])->map(fn ($n) => (string) (int) $n)->filter(fn ($n) => $n >= 15 && $n <= 50)->unique()->values()->all();
        }

        return [];
    }

    /** Colores conocidos (de la base + lista común) y los que vengan después de "color(es)". */
    public function colors(): array
    {
        $known = AttributeValue::whereHas('attribute', fn ($q) => $q->where('slug', 'color'))->pluck('value')
            ->mapWithKeys(fn ($v) => [self::fold($v) => $v]);
        foreach (self::COLORS as $c) {
            $known->put($c, $known->get($c, Str::ucfirst($c === 'cafe' ? 'café' : ($c === 'marron' ? 'marrón' : $c))));
        }
        $keys = $known->keys()->sortByDesc(fn ($k) => mb_strlen($k));

        $found = [];
        $rest = $this->a;
        foreach ($keys as $k) {
            if (preg_match('/\b'.preg_quote($k, '/').'\b/', $rest)) {
                $found[] = $known[$k];
                $rest = preg_replace('/\b'.preg_quote($k, '/').'\b/', ' ', $rest);
            }
        }

        // Colores nuevos dichos después de "color/colores": "colores terracota y oliva"
        if (preg_match('/\bcolor(?:es)?\s*(?:es|son|:)?\s*([a-z ,]+?)(?=\s*(?:\.|;|$|\btallas?\b|\bprecio\b|\bcodigo\b|\bpara\b|\ben\b|\bcon\b|\d))/', $rest, $m)) {
            foreach (preg_split('/\s*(?:,|\by\b|\be\b)\s*/', trim($m[1])) as $w) {
                $w = trim($w);
                if ($w !== '' && mb_strlen($w) >= 3 && ! preg_match('/^('.self::STOP.'|varios|todos)$/', $w)) {
                    $found[] = Str::ucfirst($w);
                }
            }
        }

        return array_values(array_unique($found));
    }

    /** Pares por cada talla/color: "3 pares de cada", "3 de cada uno", "3 por talla". */
    public function quantityEach(): ?int
    {
        if (preg_match('/\b(\d{1,4})\s*(?:pares\s*|par\s*|unidades\s*|unidad\s*)?(?:de cada|cada uno|c\/u|por (?:talla|color|variante|modelo))\b/', $this->a, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** Cantidad suelta: "5 pares", "entrada de 5", "vendí 2". */
    public function quantity(): ?int
    {
        if (preg_match('/\b(\d{1,4})\s*(?:pares|par|unidades|unidad|cajas|caja)\b/', $this->a, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\b(?:entrada|salida|ingreso|ingresaron|llegaron|llego|vendi|vendimos|vendieron|vendio|recibimos|saca|retira|suma|agrega)\s*(?:de\s*)?(\d{1,4})\b(?!\s*(?:bs|bolivianos))/', $this->a, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** Precio en bolivianos: "precio 450", "450 Bs", "Bs. 450", "cuesta 450". */
    public function price(): ?float
    {
        $n = '(\d{1,6}(?:[.,]\d{1,2})?)';
        foreach ([
            '/\bprecio\s*(?:de\s*venta\s*)?(?:es|de|:)?\s*(?:bs\.?\s*)?'.$n.'/',
            '/'.$n.'\s*(?:bs\b|bs\.|bolivianos|pesos)/',
            '/\b(?:bs\.?|bolivianos)\s*'.$n.'/',
            '/\b(?:cuesta|vale)\s+'.$n.'/',
        ] as $re) {
            if (preg_match($re, $this->a, $m)) {
                return (float) str_replace(',', '.', $m[1]);
            }
        }

        return null;
    }

    /** Tiendas mencionadas: [ids] o ['todas'] */
    public function branches(): array
    {
        if (preg_match('/\b(todas las tiendas|todas las sucursales|en todas|las tres tiendas|las 3 tiendas)\b/', $this->a)) {
            return ['todas'];
        }
        $ids = [];
        foreach (Branch::active()->get() as $b) {
            $names = array_filter([
                str_replace('-', ' ', $b->slug),
                self::fold($b->name),
                $b->slug === 'el-alto' ? 'alto' : null,
            ]);
            foreach ($names as $n) {
                if (preg_match('/\b'.preg_quote($n, '/').'\b/', $this->a)) {
                    $ids[] = $b->id;
                    break;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** Clase/categoría: "botín", "mocasines", "zapato de vestir"… (prefiere subcategorías). */
    public function category(): ?Category
    {
        $words = collect(preg_split('/[^a-z0-9]+/', $this->a))->filter()->map(fn ($w) => self::singular($w));
        $best = null;
        $bestScore = 0;
        foreach (Category::where('is_active', true)->get() as $c) {
            $keys = collect(preg_split('/[^a-z0-9]+/', self::fold($c->name)))
                ->filter(fn ($w) => mb_strlen($w) > 3)->map(fn ($w) => self::singular($w));
            $hits = $keys->intersect($words)->count();
            $score = $hits * 10 + ($c->parent_id ? 1 : 0);
            if ($hits && $score > $bestScore) {
                [$best, $bestScore] = [$c, $score];
            }
        }

        return $best;
    }

    public static function singular(string $w): string
    {
        if (mb_strlen($w) > 4 && str_ends_with($w, 'es') && preg_match('/[^aeiou]es$/', $w)) {
            return mb_substr($w, 0, -2);
        }
        if (mb_strlen($w) > 3 && str_ends_with($w, 's')) {
            return mb_substr($w, 0, -1);
        }

        return $w;
    }

    public function material(): ?string
    {
        if (preg_match('/\b(cuero(?:\s+(?:liso|graso|engrasado|nobuck|napa|charol|legitimo|natural|crazy|floter|repujado))?|gamuza|nobuck|charol|sintetico|lona)\b/', $this->a, $m, PREG_OFFSET_CAPTURE)) {
            return Str::ucfirst($this->slice($m[1][1], mb_strlen($m[1][0])));
        }

        return null;
    }

    public function layout(): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(carrusel|carousel|juntas|todas juntas|una sola)\b/', $this->a) => 'carousel',
            (bool) preg_match('/\b(separad[ao]s?|individual(?:es)?|por separado|una por una|cada una)\b/', $this->a) => 'split',
            default => null,
        };
    }

    /**
     * Nombre para un producto nuevo: lo que viene después de "nuevo / registra / llegó…"
     * hasta "código", "color", "talla", "precio", una coma, etc.
     */
    public function productName(): ?string
    {
        if (! preg_match('/\b(?:nuevo|nueva|registra(?:r)?|crea(?:r)?|da(?:r)? de alta|alta de|llego|llegaron|llego el|producto)\s+(?:el|la|un|una|producto|modelo)?\s*/u', $this->a, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = $m[0][1] + strlen($m[0][0]);

        return $this->nameFrom($start);
    }

    /** Toma el texto desde $byteOffset (en $a) hasta la primera palabra que corta. */
    public function nameFrom(int $byteOffset): ?string
    {
        $tail = substr($this->a, $byteOffset);
        $cut = preg_match('/(\s*[,.;:]|\s+(?:codigo|cod|modelo|color|colores|talla|tallas|del?\s+\d|precio|cuesta|vale|en\s+(?:calacoto|comercio|el alto|todas)|para|tienda|con\b|\d+\s*pares|material|de cuero|en cuero|cuero|gamuza|y\s+(?:publica|lo\s+publica|subelo)|publica))/', $tail, $mm, PREG_OFFSET_CAPTURE)
            ? $mm[0][1] : strlen($tail);
        $name = trim($this->slice($byteOffset, mb_strlen(substr($tail, 0, $cut))), " ,.;:-");
        if ($name === '' || mb_strlen($name) < 3) {
            return null;
        }

        return self::title($name);
    }

    public static function title(string $s): string
    {
        $small = ['de', 'del', 'la', 'el', 'y', 'con', 'en', 'para'];
        $words = explode(' ', mb_strtolower(trim($s)));
        foreach ($words as $i => $w) {
            if ($i === 0 || ! in_array($w, $small, true)) {
                $words[$i] = mb_strtoupper(mb_substr($w, 0, 1)).mb_substr($w, 1);
            }
        }

        return implode(' ', $words);
    }

    /** Texto original (con tildes) en la posición de $a (byte offset) y largo en caracteres. */
    public function slice(int $byteOffset, int $chars): string
    {
        $charOffset = mb_strlen(substr($this->a, 0, $byteOffset));

        return mb_substr($this->t, $charOffset, $chars);
    }

    /** Primer número suelto (para respuestas cortas: "450", "3"). */
    public function firstNumber(): ?float
    {
        return preg_match('/(\d+(?:[.,]\d{1,2})?)/', $this->a, $m) ? (float) str_replace(',', '.', $m[1]) : null;
    }

    /** Números de dos cifras (para respuestas cortas de tallas: "38 al 42" o "40"). */
    public function bareTallas(): array
    {
        $t = $this->tallas();
        if ($t) {
            return $t;
        }
        if (preg_match('/^\D*(\d{2})\s*(?:al?|hasta|-)\s*(\d{2})\D*$/', $this->a, $m) && $m[1] <= $m[2] && $m[2] - $m[1] <= 20) {
            return array_map('strval', range((int) $m[1], (int) $m[2]));
        }
        preg_match_all('/\b(\d{2})\b/', $this->a, $m);

        return collect($m[1])->map(fn ($n) => (string) (int) $n)->filter(fn ($n) => $n >= 15 && $n <= 50)->unique()->values()->all();
    }

    /** Lista de palabras sueltas (para respuestas cortas de colores: "café y negro"). */
    public function bareList(): Collection
    {
        return collect(preg_split('/\s*(?:,|\by\b|\be\b)\s*/u', $this->t))->map(fn ($w) => trim($w, " .;:"))->filter();
    }
}
