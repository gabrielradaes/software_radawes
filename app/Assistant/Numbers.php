<?php

namespace App\Assistant;

/** Convierte números dichos con palabras a cifras: "cuatrocientos cincuenta" → 450, "tres pares" → 3 pares. */
class Numbers
{
    private const WORDS = [
        'cero' => 0, 'uno' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5, 'seis' => 6, 'siete' => 7,
        'ocho' => 8, 'nueve' => 9, 'diez' => 10, 'once' => 11, 'doce' => 12, 'trece' => 13, 'catorce' => 14, 'quince' => 15,
        'dieciseis' => 16, 'diecisiete' => 17, 'dieciocho' => 18, 'diecinueve' => 19, 'veinte' => 20, 'veintiuno' => 21,
        'veintiun' => 21, 'veintidos' => 22, 'veintitres' => 23, 'veinticuatro' => 24, 'veinticinco' => 25, 'veintiseis' => 26,
        'veintisiete' => 27, 'veintiocho' => 28, 'veintinueve' => 29, 'treinta' => 30, 'cuarenta' => 40, 'cincuenta' => 50,
        'sesenta' => 60, 'setenta' => 70, 'ochenta' => 80, 'noventa' => 90, 'cien' => 100, 'ciento' => 100,
        'doscientos' => 200, 'trescientos' => 300, 'cuatrocientos' => 400, 'quinientos' => 500, 'seiscientos' => 600,
        'setecientos' => 700, 'ochocientos' => 800, 'novecientos' => 900, 'mil' => 1000,
        'doscientas' => 200, 'trescientas' => 300, 'cuatrocientas' => 400, 'quinientas' => 500,
    ];

    public static function toDigits(string $text): string
    {
        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = [];
        $run = [];          // índices de tokens del número en curso
        $value = 0;
        $current = 0;

        $flush = function () use (&$out, &$run, &$value, &$current, $tokens) {
            if ($run) {
                // un "y" colgando al final no es parte del número
                $last = end($run);
                $trailingY = self::key($tokens[$last]) === 'y';
                if ($trailingY) {
                    array_pop($run);
                }
                $out[] = (string) ($value + $current);
                if ($trailingY) {
                    $out[] = ' ';
                    $out[] = $tokens[$last];
                }
            }
            $run = [];
            $value = 0;
            $current = 0;
        };

        $n = count($tokens);
        for ($i = 0; $i < $n; $i++) {
            $tok = $tokens[$i];
            if ($i % 2 === 1) {            // separador (espacios)
                if (! $run) {
                    $out[] = $tok;
                }
                continue;
            }
            $key = self::key($tok);
            $next = self::key($tokens[$i + 2] ?? '');
            $punct = preg_match('/[,.;:!?]$/u', $tok) ? mb_substr($tok, -1) : '';

            $prev = self::key($tokens[$i - 2] ?? '');
            $isNumber = (isset(self::WORDS[$key]) && ! ($key === 'uno' && in_array($prev, ['cada', 'el', 'otro', 'ninguno'], true)))
                || (in_array($key, ['un', 'una'], true) && in_array($next, ['par', 'mil'], true));
            $isJoin = $key === 'y' && $run && isset(self::WORDS[$next]) && self::WORDS[$next] < 10 && $current % 100 >= 30;

            if ($isNumber) {
                $w = self::WORDS[$key] ?? 1;
                if ($w === 1000) {
                    $value += max($current, 1) * 1000;
                    $current = 0;
                } else {
                    $current += $w;
                }
                $run[] = $i;
                if ($punct) {
                    $flush();
                    $out[] = $punct;
                }
            } elseif ($isJoin) {
                $run[] = $i;
            } else {
                if ($run) {
                    $flush();
                    $out[] = $tokens[$i - 1];   // espacio previo
                }
                $out[] = $tok;
            }
        }
        $flush();

        return implode('', $out);
    }

    private static function key(string $tok): string
    {
        $tok = mb_strtolower(trim($tok, " ,.;:!?¿¡"));

        return strtr($tok, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    }
}
