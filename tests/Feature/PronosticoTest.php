<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\ForecastService;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Pronóstico de ventas: aprende la temporada del historial y sugiere compras por talla. */
class PronosticoTest extends TestCase
{
    /** Crea ventas mensuales de los últimos 24 meses con más ventas en diciembre. */
    private function historial(): void
    {
        $p = Product::create(['category_id' => Category::where('slug', 'botines')->value('id'), 'name' => 'Botín Chelsea', 'code' => 'CH-210',
            'slug' => 'botin-chelsea', 'base_price' => 450, 'is_active' => true, 'published_at' => now()]);
        PurchaseService::register($this->admin(), $this->branch('calacoto'), null, [['product' => $p, 'color' => 'Negro', 'sizes' => ['40' => 2, '41' => 1], 'unit_cost' => 250]]);
        $v40 = $p->variants()->with('attributeValues.attribute')->get()->first(fn ($v) => $v->valueOf('talla') === '40');
        $v41 = $p->variants()->with('attributeValues.attribute')->get()->first(fn ($v) => $v->valueOf('talla') === '41');
        $user = $this->admin()->id;
        $branch = $this->branch('calacoto')->id;
        $id = 0;
        for ($i = 1; $i <= 24; $i++) {
            $d = now()->startOfMonth()->subMonths($i)->addDays(10);
            $n = $d->month === 12 ? 20 : 10;      // diciembre vende el doble
            for ($k = 0; $k < $n; $k++) {
                $saleId = DB::table('sales')->insertGetId(['code' => 'H-'.(++$id), 'branch_id' => $branch, 'user_id' => $user, 'status' => 'pagada', 'channel' => 'tienda',
                    'payment_method' => 'efectivo', 'subtotal' => 450, 'discount' => 0, 'total' => 450, 'sold_at' => $d, 'created_at' => $d, 'updated_at' => $d]);
                DB::table('sale_items')->insert(['sale_id' => $saleId, 'product_variant_id' => ($k % 4 === 0 ? $v41 : $v40)->id, 'quantity' => 1, 'unit_price' => 450,
                    'unit_cost' => 250, 'discount' => 0, 'line_total' => 450, 'created_at' => $d, 'updated_at' => $d]);
            }
        }
    }

    public function test_aprende_la_temporada_y_pronostica(): void
    {
        $this->historial();
        $d = ForecastService::build(null);

        $this->assertSame(24, $d['history']);
        $this->assertSame('alta', $d['confidence']);
        $this->assertGreaterThan(1.6, $d['season'][12], 'Diciembre vende más que el promedio');
        $this->assertLessThan(1.0, $d['season'][4]);
        $dec = collect($d['forecast'])->firstWhere('month', 12);
        $other = collect($d['forecast'])->first(fn ($f) => $f['month'] !== 12);
        if ($dec) {
            $this->assertGreaterThan($other['pares'], $dec['pares']);
        }
        $this->assertSame('Botín Chelsea', $d['topModels']['rows'][0]->name);
        $talla40 = collect($d['buy'])->firstWhere('talla', '40');
        $this->assertEqualsWithDelta(0.75, $talla40['share'], 0.05, 'La 40 es el 75 % de lo vendido');
        $this->assertSame(max(0, $talla40['expected'] - 2), $talla40['buy'], 'Pedir = esperado − disponible');
    }

    public function test_sin_historial_usa_el_perfil_tipico_y_la_pagina_carga(): void
    {
        $d = ForecastService::build(null);
        $this->assertSame(0, $d['history']);
        $this->assertSame(ForecastService::PRIOR[12], $d['season'][12]);

        $this->actingAs($this->admin())->get('/admin/pronostico')->assertOk()->assertSee('Todavía hay pocas ventas')->assertSee('Fechas que vienen');
        $this->actingAs($this->supervisor('calacoto'))->get('/admin/pronostico')->assertOk();
        $this->actingAs($this->vendedor('calacoto'))->get('/admin/pronostico')->assertForbidden();
    }

    public function test_fechas_moviles_y_temporadas(): void
    {
        $carnaval = collect(ForecastService::events(2027))->firstWhere('name', 'Carnaval');
        $this->assertSame('2027-02-08', $carnaval['date']->toDateString());   // Pascua 2027: 28 de marzo
        $this->assertSame('Época de lluvias', ForecastService::seasonOf(1)['name']);
        $this->assertSame('Invierno (frío)', ForecastService::seasonOf(7)['name']);
    }
}
