<?php

namespace App\Assistant;

use Illuminate\Support\Carbon;

/**
 * Fechas dichas en español (texto ya en minúsculas, sin tildes y con números en cifras):
 * "hoy", "mañana", "pasado mañana", "el viernes", "en 3 dias", "la proxima semana", "el 25", "25 de noviembre", "25/11",
 * con hora opcional: "a las 10", "a las 3 de la tarde", "10:30", "al mediodia", "en la tarde".
 */
class Dates
{
    private const DAYS = ['domingo' => 0, 'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sabado' => 6];

    private const MONTHS = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7,
        'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12];

    /** @return array{at: Carbon, spans: string[]}|null  spans = fragmentos del texto que eran la fecha/hora */
    public static function parse(string $a, ?Carbon $now = null): ?array
    {
        $now = ($now ?? now())->copy();
        $date = null;
        $spans = [];

        $months = implode('|', array_keys(self::MONTHS));
        $days = implode('|', array_keys(self::DAYS));

        if (preg_match('/\bpasado\s+manana\b/', $a, $m)) {
            $date = $now->copy()->addDays(2);
            $spans[] = $m[0];
        } elseif (preg_match('/\b(?<!la |por la |en la |de la )manana\b/', $a, $m)) {
            $date = $now->copy()->addDay();
            $spans[] = $m[0];
        } elseif (preg_match('/\b(?:hoy|esta (?:tarde|noche|manana))\b/', $a, $m)) {
            $date = $now->copy();
            $spans[] = 'hoy';
        } elseif (preg_match('/\b(?:en|dentro de)\s+(\d{1,2})\s+(dias?|semanas?|mes(?:es)?)\b/', $a, $m)) {
            $n = (int) $m[1];
            $date = match (true) {
                str_starts_with($m[2], 'dia') => $now->copy()->addDays($n),
                str_starts_with($m[2], 'semana') => $now->copy()->addWeeks($n),
                default => $now->copy()->addMonthsNoOverflow($n),
            };
            $spans[] = $m[0];
        } elseif (preg_match('/\b(?:la\s+)?(?:proxima|otra|siguiente)\s+semana\b/', $a, $m)) {
            $date = $now->copy()->next(Carbon::MONDAY);
            $spans[] = $m[0];
        } elseif (preg_match('/\b(?:el\s+)?(\d{1,2})\s+de\s+('.$months.')\b/', $a, $m)) {
            $date = self::future($now, (int) $m[1], self::MONTHS[$m[2]]);
            $spans[] = $m[0];
        } elseif (preg_match('/\b(\d{1,2})\/(\d{1,2})\b/', $a, $m) && $m[2] <= 12) {
            $date = self::future($now, (int) $m[1], (int) $m[2]);
            $spans[] = $m[0];
        } elseif (preg_match('/\b(?:el\s+)?(?:proximo\s+)?('.$days.')\b/', $a, $m)) {
            $target = self::DAYS[$m[1]];
            $date = $now->dayOfWeek === $target ? $now->copy()->addWeek() : $now->copy()->next($target);
            $spans[] = $m[0];
        } elseif (preg_match('/\bel\s+(?:dia\s+)?(\d{1,2})\b(?!\s*(?:pares|par|bs|de cada|:))/', $a, $m) && $m[1] >= 1 && $m[1] <= 31) {
            $date = self::future($now, (int) $m[1], $now->month, false);
            $spans[] = $m[0];
        }

        // Hora
        [$hour, $minute, $timeSpan] = self::time($a);
        if ($timeSpan) {
            $spans[] = $timeSpan;
        }
        if (! $date && $hour === null) {
            return null;
        }
        $date ??= $now->copy();
        $date->setTime($hour ?? 10, $minute ?? 0);
        if ($date->lte($now)) {
            $date = $hour === null && $date->isToday() ? $now->copy()->addHour()->startOfHour() : $date->addDay();
        }

        return ['at' => $date, 'spans' => $spans];
    }

    /** @return array{0: ?int, 1: ?int, 2: ?string} */
    private static function time(string $a): array
    {
        if (preg_match('/\b(?:a\s+las|a\s+la|las)\s+(\d{1,2})(?::(\d{2}))?\s*(?:hrs?|horas)?\s*(?:(de la|en la|por la)\s+(manana|tarde|noche)|(am|pm))?/', $a, $m)) {
            $h = (int) $m[1];
            $period = $m[4] ?? ($m[5] ?? '');
            if (in_array($period, ['tarde', 'noche', 'pm'], true) && $h < 12) {
                $h += 12;
            } elseif ($period === '' && $h >= 1 && $h <= 7) {
                $h += 12;   // "a las 3" en una tienda = 15:00
            }

            return [min($h, 23), isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0, $m[0]];
        }
        if (preg_match('/\b(\d{1,2}):(\d{2})\b/', $a, $m)) {
            return [(int) $m[1], (int) $m[2], $m[0]];
        }
        if (preg_match('/\b(?:al\s+)?mediodia\b/', $a, $m)) {
            return [12, 0, $m[0]];
        }
        if (preg_match('/\b(?:en|por|de)\s+la\s+(manana|tarde|noche)\b/', $a, $m)) {
            return [['manana' => 9, 'tarde' => 15, 'noche' => 19][$m[1]], 0, $m[0]];
        }

        return [null, null, null];
    }

    /** Día/mes dado; si ya pasó: con mes explícito → el próximo año, solo el día → el próximo mes. */
    private static function future(Carbon $now, int $day, int $month, bool $explicitMonth = true): Carbon
    {
        $make = fn (int $y, int $mo) => Carbon::create($y, $mo, 1, 0, 0, 0, $now->timezone)->day(min($day, Carbon::create($y, $mo, 1)->daysInMonth));
        $d = $make($now->year, $month);
        if ($d->lt($now->copy()->startOfDay())) {
            $d = $explicitMonth ? $make($now->year + 1, $month) : $make($now->copy()->startOfMonth()->addMonth()->year, $now->copy()->startOfMonth()->addMonth()->month);
        }

        return $d;
    }
}
