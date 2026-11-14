<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Estructura del catálogo SIN productos: categorías, clases de calzado, marcas, tallas y colores.
 * Es lo que necesita la base de datos de producción para empezar a cargar productos reales.
 */
class BaseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $calzado = Category::updateOrCreate(['slug' => 'calzado'], ['name' => 'Calzado', 'icon' => 'shoe', 'sort_order' => 1, 'is_active' => true, 'description' => 'Zapatos de cuero hechos en Brasil.']);
        $clases = [
            'de-traje' => 'De traje y vestir',
            'mocasines' => 'Mocasines',
            'botines' => 'Botines',
            'botas' => 'Botas',
            'montaneras' => 'Montañeras',
            'zapatenis' => 'Zapatenis',
            'casuales' => 'Casuales y urbanos',
            'deportivos' => 'Deportivos',
            'sandalias' => 'Sandalias',
        ];
        $i = 1;
        foreach ($clases as $slug => $name) {
            Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'parent_id' => $calzado->id, 'sort_order' => $i++, 'is_active' => true]);
        }

        $motos = Category::updateOrCreate(['slug' => 'motos'], ['name' => 'Motos', 'icon' => 'moto', 'sort_order' => 2, 'is_active' => true, 'description' => 'Motocicletas y scooters.']);
        Category::updateOrCreate(['slug' => 'scooters'], ['name' => 'Scooters', 'parent_id' => $motos->id, 'sort_order' => 1, 'is_active' => true]);
        Category::updateOrCreate(['slug' => 'accesorios'], ['name' => 'Accesorios', 'icon' => 'bag', 'sort_order' => 3, 'is_active' => true, 'description' => 'Cinturones, billeteras y cuidado del calzado.']);

        Brand::updateOrCreate(['slug' => 'radawes'], ['name' => 'RadaWes', 'country' => 'Brasil']);
        Brand::updateOrCreate(['slug' => 'importado'], ['name' => 'Importado', 'country' => null]);

        $talla = Attribute::updateOrCreate(['slug' => 'talla'], ['name' => 'Talla']);
        foreach (range(35, 45) as $n => $t) {
            AttributeValue::updateOrCreate(['attribute_id' => $talla->id, 'value' => (string) $t], ['sort_order' => $n]);
        }

        $color = Attribute::updateOrCreate(['slug' => 'color'], ['name' => 'Color']);
        $paleta = ['Negro' => '#111111', 'Café' => '#6b3e1e', 'Miel' => '#b5752e', 'Guinda' => '#5c1a24', 'Azul marino' => '#1b2a4a', 'Blanco' => '#f2f2f2', 'Gris' => '#777777', 'Rojo' => '#b3121b', 'Beige' => '#d8c3a5', 'Plomo' => '#5a5a5a'];
        $n = 0;
        foreach ($paleta as $nombre => $hex) {
            AttributeValue::updateOrCreate(['attribute_id' => $color->id, 'value' => $nombre], ['meta' => $hex, 'sort_order' => $n++]);
        }
    }
}
