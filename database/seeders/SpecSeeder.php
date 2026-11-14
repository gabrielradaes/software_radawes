<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\SpecField;
use Illuminate\Database\Seeder;

class SpecSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            'calzado' => [
                ['Material exterior', null, 'Cuero legítimo bovino'],
                ['Forro interior', null, 'Cuero / textil'],
                ['Suela', null, 'Goma antideslizante / cuero cosido'],
                ['Cierre', null, 'Cordones / hebilla / elástico / sin cordones'],
                ['Horma', null, 'Clásica / ancha / punta fina'],
                ['Altura de taco', 'cm', '2.5'],
                ['Ocasión', null, 'Formal / casual / fiesta'],
                ['Peso por par', 'g', '850'],
                ['Cuidado', null, 'Crema para cuero, evitar agua'],
                ['Garantía', null, '30 días por defectos de fábrica'],
            ],
            'motos' => [
                ['Cilindrada', 'cc', '150'],
                ['Tipo de motor', null, '4 tiempos, monocilíndrico, refrigerado por aire'],
                ['Potencia', 'HP', '9.5'],
                ['Transmisión', null, 'Automática CVT / manual 5 velocidades'],
                ['Arranque', null, 'Eléctrico y de patada'],
                ['Frenos', null, 'Disco delantero / tambor trasero'],
                ['Capacidad de tanque', 'L', '6'],
                ['Consumo', 'km/L', '40'],
                ['Peso', 'kg', '110'],
                ['Velocidad máxima', 'km/h', '95'],
                ['Año / modelo', null, '2025'],
                ['Llantas', null, '90/90-12 delantera, 100/90-10 trasera'],
                ['Documentación incluida', null, 'Factura, placa y SOAT en trámite'],
                ['Garantía', null, '6 meses o 5.000 km'],
            ],
            'accesorios' => [
                ['Material', null, 'Cuero legítimo'],
                ['Medidas', 'cm', '110 x 3.5'],
                ['Peso', 'g', '120'],
                ['Garantía', null, '30 días'],
            ],
        ];

        $ids = [];
        foreach ($fields as $slug => $list) {
            $cat = Category::where('slug', $slug)->first();
            if (! $cat) {
                continue;
            }
            foreach ($list as $i => [$name, $unit, $placeholder]) {
                $f = SpecField::updateOrCreate(['category_id' => $cat->id, 'name' => $name], [
                    'unit' => $unit, 'placeholder' => $placeholder, 'sort_order' => $i + 1,
                ]);
                $ids[$slug][$name] = $f->id;
            }
        }

        // Valores de ejemplo para los productos cargados por el seed
        $calzado = [
            'Suela' => 'Goma antideslizante cosida',
            'Forro interior' => 'Cuero',
            'Horma' => 'Clásica',
            'Altura de taco' => '2.5',
            'Ocasión' => 'Formal y oficina',
            'Cuidado' => 'Crema para cuero; evitar lluvia intensa',
            'Garantía' => '30 días por defectos de fábrica',
        ];
        $cierres = [
            'zapato-oxford-clasico' => 'Cordones', 'zapato-derby-brogue' => 'Cordones', 'zapato-monk-doble-hebilla' => 'Doble hebilla',
            'oxford-charol-de-gala' => 'Cordones', 'mocasin-penny' => 'Sin cordones', 'mocasin-con-hebilla' => 'Hebilla lateral',
            'botin-chelsea' => 'Elástico lateral', 'botin-con-cordones' => 'Cordones', 'zapatilla-urbana-de-cuero' => 'Cordones',
            'nautico-clasico' => 'Cordones laterales', 'sandalia-de-cuero' => 'Tiras ajustables',
        ];
        foreach (Product::with('category.parent')->get() as $p) {
            $root = $p->category->root()->slug;
            $values = match ($root) {
                'calzado' => [...$calzado, 'Material exterior' => $p->material ?? 'Cuero legítimo', 'Cierre' => $cierres[$p->slug] ?? 'Cordones',
                    'Ocasión' => str_contains($p->slug, 'botin') || str_contains($p->slug, 'zapatilla') || str_contains($p->slug, 'nautico') || str_contains($p->slug, 'sandalia') ? 'Casual' : ($p->slug === 'oxford-charol-de-gala' ? 'Fiesta y gala' : 'Formal y oficina')],
                'motos' => ['Cilindrada' => '150', 'Tipo de motor' => '4 tiempos, monocilíndrico, refrigerado por aire', 'Potencia' => '9.5', 'Transmisión' => 'Automática CVT',
                    'Arranque' => 'Eléctrico y de patada', 'Frenos' => 'Disco delantero / tambor trasero', 'Capacidad de tanque' => '6', 'Consumo' => '40',
                    'Peso' => '110', 'Velocidad máxima' => '95', 'Año / modelo' => '2025', 'Documentación incluida' => 'Factura; placa y SOAT en trámite', 'Garantía' => '6 meses o 5.000 km'],
                'accesorios' => ['Material' => 'Cuero legítimo', 'Garantía' => '30 días'],
                default => [],
            };
            foreach ($values as $name => $value) {
                if (isset($ids[$root][$name])) {
                    $p->specs()->updateOrCreate(['spec_field_id' => $ids[$root][$name]], ['value' => $value]);
                }
            }
        }
    }
}
