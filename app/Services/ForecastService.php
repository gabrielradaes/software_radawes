<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pronóstico de ventas (gratis, sin IA de pago) a partir del historial:
 *  1. Serie mensual de pares vendidos (24 meses).
 *  2. Estacionalidad: cuánto vende cada mes del año frente al promedio. Con menos de 2 años de datos
 *     se mezcla con un perfil típico de zapatería en Bolivia (Navidad, Día del Padre/Madre, clases…).
 *  3. Nivel actual (promedio de los últimos 3 meses sin estacionalidad) × tendencia × estacionalidad = pronóstico.
 *  4. Para el mes que viene: qué clases y modelos se venden en esa época y cuántos pares por talla conviene pedir.
 */
class ForecastService
{
    /** Perfil típico de calzado en Bolivia (1 = mes promedio). Se usa mientras no haya 2 años de historial propio. */
    public const PRIOR = [1 => 0.85, 2 => 0.95, 3 => 1.10, 4 => 0.85, 5 => 1.20, 6 => 1.00, 7 => 0.95, 8 => 0.90, 9 => 0.95, 10 => 1.00, 11 => 1.05, 12 => 1.60];

    /** Fechas que mueven las ventas (Bolivia) y temporadas de clima de La Paz / El Alto. */
    public static function events(int $year): array
    {
        $easter = Carbon::createFromTimestamp(easter_date_safe($year))->startOfDay();

        return [
            ['date' => Carbon::create($year, 1, 1), 'name' => 'Año Nuevo', 'tip' => 'Liquidar lo que quedó de diciembre.'],
            ['date' => Carbon::create($year, 2, 3), 'name' => 'Regreso a clases', 'tip' => 'Zapatos escolares y de vestir económicos.'],
            ['date' => $easter->copy()->subDays(48), 'name' => 'Carnaval', 'tip' => 'Calzado cómodo y casual.'],
            ['date' => Carbon::create($year, 3, 19), 'name' => 'Día del Padre', 'tip' => 'Zapatos de vestir, mocasines y botines de caballero.'],
            ['date' => Carbon::create($year, 5, 27), 'name' => 'Día de la Madre', 'tip' => 'Calzado de dama, botines y regalos.'],
            ['date' => Carbon::create($year, 9, 21), 'name' => 'Día del Estudiante / primavera', 'tip' => 'Zapatenis y casuales.'],
            ['date' => Carbon::create($year, 10, 11), 'name' => 'Día de la Mujer Boliviana', 'tip' => 'Calzado de dama.'],
            ['date' => Carbon::create($year, 12, 25), 'name' => 'Navidad y aguinaldo', 'tip' => 'El mes más fuerte: tener todas las tallas de los más vendidos.'],
        ];
    }

    public static function seasonOf(int $month): array
    {
        return match (true) {
            in_array($month, [12, 1, 2, 3], true) => ['name' => 'Época de lluvias', 'icon' => '🌧️', 'classes' => ['bota', 'botin'], 'tip' => 'Se venden más botas y botines de cuero (resisten el agua).'],
            in_array($month, [5, 6, 7, 8], true) => ['name' => 'Invierno (frío)', 'icon' => '❄️', 'classes' => ['bota', 'botin', 'montanera'], 'tip' => 'Botas, botines y montañeras: el frío de La Paz y El Alto.'],
            default => ['name' => 'Época templada / calor', 'icon' => '☀️', 'classes' => ['mocasin', 'casual', 'zapateni', 'sandalia'], 'tip' => 'Mocasines, casuales, zapatenis y sandalias.'],
        };
    }

    /** Pares vendidos por mes (clave Y-m) en los últimos $months meses. */
    public static function monthly(?int $branchId, int $months = 24): Collection
    {
        $from = now()->startOfMonth()->subMonths($months - 1);
        $rows = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'pagada')->where('sales.sold_at', '>=', $from)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->selectRaw("DATE_FORMAT(sales.sold_at, '%Y-%m') as ym, SUM(sale_items.quantity) as pares, SUM(sale_items.line_total) as monto")
            ->groupBy('ym')->get()->keyBy('ym');

        return collect(range(0, $months - 1))->mapWithKeys(function ($i) use ($from, $rows) {
            $ym = $from->copy()->addMonths($i)->format('Y-m');

            return [$ym => ['pares' => (int) ($rows[$ym]->pares ?? 0), 'monto' => (float) ($rows[$ym]->monto ?? 0)]];
        });
    }

    public static function build(?int $branchId): array
    {
        $series = self::monthly($branchId, 25);                    // 24 meses completos + el mes en curso
        $current = now()->format('Y-m');
        $complete = $series->except($current);                     // el mes en curso aún no terminó
        $withSales = $complete->filter(fn ($m) => $m['pares'] > 0);
        $history = $withSales->count();                              // meses con ventas
        $avg = $history ? $withSales->avg('pares') : 0;

        // Estacionalidad propia por mes del año, mezclada con el perfil típico según cuánto historial hay
        $own = [];
        foreach ($withSales as $ym => $m) {
            $own[(int) substr($ym, 5, 2)][] = $avg > 0 ? $m['pares'] / $avg : 1;
        }
        $weight = min(1, $history / 24);
        $season = [];
        foreach (range(1, 12) as $mo) {
            $mine = isset($own[$mo]) ? array_sum($own[$mo]) / count($own[$mo]) : self::PRIOR[$mo];
            $season[$mo] = round($weight * $mine + (1 - $weight) * self::PRIOR[$mo], 3);
        }

        // Nivel: promedio de los últimos 3 meses completos, quitando la estacionalidad
        $last3 = $complete->take(-3)->filter(fn ($m) => $m['pares'] > 0);
        $level = $last3->isEmpty() ? 0 : $last3->map(fn ($m, $ym) => $m['pares'] / max(0.3, $season[(int) substr($ym, 5, 2)]))->avg();
        // Poco historial: usar el ritmo del mes en curso (ventas hasta hoy proyectadas al mes completo)
        $soFar = ($series[$current]['pares'] ?? 0);
        if ($history < 3 && $soFar > 0) {
            $pace = $soFar / max(1, now()->day) * now()->daysInMonth / max(0.3, $season[now()->month]);
            $level = $history ? ($level + $pace) / 2 : $pace;
        }
        // Tendencia: últimos 6 meses frente a los 6 anteriores (sin estacionalidad), limitada a ±30 %
        $deseason = $complete->map(fn ($m, $ym) => $m['pares'] / max(0.3, $season[(int) substr($ym, 5, 2)]))->values();
        $recent = $deseason->take(-6)->avg();
        $before = $deseason->slice(-12, 6)->avg();
        $trend = ($before > 0 && $history >= 12) ? max(-0.3, min(0.3, $recent / $before - 1)) : 0;

        $forecast = [];
        foreach (range(0, 2) as $i) {
            $d = now()->startOfMonth()->addMonths($i);
            $pares = (int) round($level * (1 + $trend * ($i + 1) / 6) * $season[$d->month]);
            $forecast[$d->format('Y-m')] = ['pares' => $pares, 'label' => $d->isoFormat('MMMM YYYY'), 'month' => $d->month,
                'low' => (int) floor($pares * 0.8), 'high' => (int) ceil($pares * 1.2)];
        }

        $next = now()->startOfMonth()->addMonth();
        $nextPairs = $forecast[$next->format('Y-m')]['pares'];

        return [
            'series' => $complete->take(-12),
            'currentMonth' => $series[$current] ?? ['pares' => 0, 'monto' => 0],
            'forecast' => $forecast,
            'season' => $season,
            'history' => $history,
            'trend' => $trend,
            'confidence' => $history >= 24 ? 'alta' : ($history >= 12 ? 'media' : 'baja'),
            'events' => collect([...self::events(now()->year), ...self::events(now()->year + 1)])
                ->filter(fn ($e) => $e['date']->between(today(), today()->addDays(90)))->values(),
            'weather' => self::seasonOf($next->month),
            'nextMonth' => $next,
            'byClass' => self::byClassFor($branchId, $next, $nextPairs),
            'topModels' => self::topModelsFor($branchId, $next),
            'buy' => self::buySuggestion($branchId, $next, $nextPairs),
        ];
    }

    /** Ventana de referencia: el mismo mes del año pasado si hay datos; si no, los últimos 3 meses. */
    private static function window(?int $branchId, Carbon $month): array
    {
        $ly = [$month->copy()->subYear()->startOfMonth(), $month->copy()->subYear()->endOfMonth()];
        $has = DB::table('sales')->where('status', 'pagada')->whereBetween('sold_at', $ly)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->exists();

        return $has ? [$ly, 'el mismo mes del año pasado'] : [[now()->subMonths(3)->startOfDay(), now()], 'los últimos 3 meses'];
    }

    private static function itemsIn(?int $branchId, array $range)
    {
        return DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('product_variants as pv', 'pv.id', '=', 'sale_items.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('sales.status', 'pagada')->whereBetween('sales.sold_at', $range)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId));
    }

    private static function byClassFor(?int $branchId, Carbon $month, int $pairs): array
    {
        [$range, $basis] = self::window($branchId, $month);
        $rows = self::itemsIn($branchId, $range)->join('categories as c', 'c.id', '=', 'p.category_id')
            ->groupBy('c.id', 'c.name')->selectRaw('c.name, SUM(sale_items.quantity) as pares')->orderByDesc('pares')->limit(8)->get();
        $total = max(1, $rows->sum('pares'));

        return ['basis' => $basis, 'rows' => $rows->map(fn ($r) => ['name' => $r->name, 'share' => $r->pares / $total, 'expected' => (int) round($pairs * $r->pares / $total)])->all()];
    }

    private static function topModelsFor(?int $branchId, Carbon $month): array
    {
        [$range, $basis] = self::window($branchId, $month);
        $rows = self::itemsIn($branchId, $range)->groupBy('p.id', 'p.name', 'p.code')
            ->selectRaw('p.id, p.name, p.code, SUM(sale_items.quantity) as pares')->orderByDesc('pares')->limit(10)->get();

        return ['basis' => $basis, 'rows' => $rows->all()];
    }

    /**
     * Compra sugerida por talla para el mes que viene: pares esperados repartidos según las tallas que más se venden,
     * menos lo que ya hay disponible. Solo para las tallas de calzado.
     */
    private static function buySuggestion(?int $branchId, Carbon $month, int $pairs): array
    {
        $tallaId = DB::table('attributes')->where('slug', 'talla')->value('id');
        $sizesSold = self::itemsIn($branchId, [now()->subYear()->startOfDay(), now()])
            ->join('attribute_value_product_variant as avp', 'avp.product_variant_id', '=', 'sale_items.product_variant_id')
            ->join('attribute_values as av', 'av.id', '=', 'avp.attribute_value_id')->where('av.attribute_id', $tallaId)
            ->groupBy('av.value')->selectRaw('av.value as talla, SUM(sale_items.quantity) as pares')->get();
        $total = max(1, $sizesSold->sum('pares'));
        $stock = DB::table('stocks')->join('attribute_value_product_variant as avp', 'avp.product_variant_id', '=', 'stocks.product_variant_id')
            ->join('attribute_values as av', 'av.id', '=', 'avp.attribute_value_id')->where('av.attribute_id', $tallaId)
            ->when($branchId, fn ($q) => $q->where('stocks.branch_id', $branchId))
            ->groupBy('av.value')->selectRaw('av.value as talla, SUM(GREATEST(stocks.quantity - stocks.reserved, 0)) as disponible')->pluck('disponible', 'talla');

        return $sizesSold->map(function ($r) use ($total, $pairs, $stock) {
            $expected = (int) round($pairs * $r->pares / $total);
            $have = (int) ($stock[$r->talla] ?? 0);

            return ['talla' => $r->talla, 'share' => $r->pares / $total, 'expected' => $expected, 'stock' => $have, 'buy' => max(0, $expected - $have)];
        })->sortBy(fn ($r) => (int) $r['talla'])->values()->all();
    }
}

if (! function_exists('easter_date_safe')) {
    /** Domingo de Pascua (algoritmo de Gauss/Meeus) sin depender de la extensión calendar. */
    function easter_date_safe(int $year): int
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return mktime(12, 0, 0, $month, $day, $year);
    }
}
