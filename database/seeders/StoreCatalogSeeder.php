<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Database\Seeder;

/**
 * Clases de calzado y asignación de productos a cada tienda.
 */
class StoreCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $calzado = Category::where('slug', 'calzado')->firstOrFail();

        // Clases de calzado (subcategorías). El admin/supervisor puede crear más desde el panel.
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
        // Renombrar la clase original "zapatos-de-vestir" a "de-traje"
        Category::where('slug', 'zapatos-de-vestir')->update(['slug' => 'de-traje', 'name' => 'De traje y vestir']);
        $i = 1;
        foreach ($clases as $slug => $name) {
            Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'parent_id' => $calzado->id, 'sort_order' => $i++, 'is_active' => true]);
        }

        // Qué vende cada tienda
        $comercio = Branch::where('slug', 'comercio')->first();
        $calacoto = Branch::where('slug', 'calacoto')->first();
        $elAlto = Branch::where('slug', 'el-alto')->first();

        foreach (Product::with('category')->get() as $p) {
            $root = $p->category->root()->slug;
            $stores = match ($root) {
                'motos' => [$elAlto],
                'accesorios' => [$comercio, $elAlto],
                default => [$comercio, $calacoto, $elAlto],   // calzado en las tres
            };
            $p->branches()->syncWithoutDetaching(collect($stores)->filter()->pluck('id')->mapWithKeys(fn ($id) => [$id => ['is_active' => true]])->all());

            // Filas de stock (en 0) para cada variante en cada tienda donde se vende
            foreach ($p->variants as $v) {
                foreach ($stores as $b) {
                    if ($b) {
                        Stock::firstOrCreate(['product_variant_id' => $v->id, 'branch_id' => $b->id], ['quantity' => 0, 'min_quantity' => 1]);
                    }
                }
            }
        }
    }
}
