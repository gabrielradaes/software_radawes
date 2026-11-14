<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('slug', 'comercio')->firstOrFail();
        $admin = User::where('email', 'admin@rye.test')->firstOrFail();

        // ---------- Categorías ----------
        $calzado = Category::updateOrCreate(['slug' => 'calzado'], ['name' => 'Calzado', 'icon' => 'shoe', 'sort_order' => 1, 'description' => 'Zapatos de cuero para hombre hechos en Brasil.']);
        $subs = [
            'zapatos-de-vestir' => 'Zapatos de vestir',
            'mocasines' => 'Mocasines',
            'botines' => 'Botines',
            'casuales' => 'Casuales y urbanos',
            'sandalias' => 'Sandalias',
        ];
        $cat = [];
        $i = 1;
        foreach ($subs as $slug => $name) {
            $cat[$slug] = Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'parent_id' => $calzado->id, 'sort_order' => $i++]);
        }
        $motos = Category::updateOrCreate(['slug' => 'motos'], ['name' => 'Motos', 'icon' => 'moto', 'sort_order' => 2, 'description' => 'Motocicletas y scooters.']);
        $cat['scooters'] = Category::updateOrCreate(['slug' => 'scooters'], ['name' => 'Scooters', 'parent_id' => $motos->id, 'sort_order' => 1]);
        $accesorios = Category::updateOrCreate(['slug' => 'accesorios'], ['name' => 'Accesorios', 'icon' => 'bag', 'sort_order' => 3, 'description' => 'Cinturones, billeteras y cuidado del calzado.']);
        $cat['accesorios'] = $accesorios;

        // ---------- Marcas ----------
        $radawes = Brand::updateOrCreate(['slug' => 'radawes'], ['name' => 'RadaWes', 'country' => 'Brasil']);
        $generic = Brand::updateOrCreate(['slug' => 'importado'], ['name' => 'Importado', 'country' => null]);

        // ---------- Atributos ----------
        $talla = Attribute::updateOrCreate(['slug' => 'talla'], ['name' => 'Talla']);
        $color = Attribute::updateOrCreate(['slug' => 'color'], ['name' => 'Color']);
        $tallas = [];
        foreach (range(38, 44) as $n => $t) {
            $tallas[$t] = AttributeValue::updateOrCreate(['attribute_id' => $talla->id, 'value' => (string) $t], ['sort_order' => $n]);
        }
        $colores = [];
        $paleta = ['Negro' => '#111111', 'Café' => '#6b3e1e', 'Miel' => '#b5752e', 'Guinda' => '#5c1a24', 'Azul marino' => '#1b2a4a', 'Blanco' => '#f2f2f2', 'Gris' => '#777777', 'Rojo' => '#b3121b'];
        $n = 0;
        foreach ($paleta as $nombre => $hex) {
            $colores[$nombre] = AttributeValue::updateOrCreate(['attribute_id' => $color->id, 'value' => $nombre], ['meta' => $hex, 'sort_order' => $n++]);
        }

        // ---------- Productos ----------
        $productos = [
            ['Zapato Oxford Clásico', 'zapatos-de-vestir', 420, null, ['Negro', 'Café'], 'Cuero legítimo', 'El clásico infaltable para traje. Suela cosida y plantilla acolchada.', true],
            ['Zapato Derby Brogue', 'zapatos-de-vestir', 450, 520, ['Miel', 'Café'], 'Cuero legítimo', 'Perforado estilo brogue, elegante sin ser formal en exceso.', true],
            ['Zapato Monk Doble Hebilla', 'zapatos-de-vestir', 470, null, ['Negro', 'Guinda'], 'Cuero legítimo', 'Doble hebilla metálica. Para quien quiere destacar con clase.', false],
            ['Oxford Charol de Gala', 'zapatos-de-vestir', 460, null, ['Negro'], 'Charol', 'Brillo impecable para fiestas, bodas y graduaciones.', false],
            ['Mocasín Penny', 'mocasines', 380, null, ['Café', 'Negro', 'Miel'], 'Cuero legítimo', 'Cómodo, sin cordones, ideal para oficina y fin de semana.', true],
            ['Mocasín con Hebilla', 'mocasines', 395, 440, ['Guinda', 'Negro'], 'Cuero legítimo', 'Hebilla lateral y horma anatómica. Elegancia relajada.', false],
            ['Botín Chelsea', 'botines', 490, null, ['Negro', 'Café'], 'Cuero legítimo', 'Elástico lateral y tirador posterior. Perfecto para el frío paceño.', true],
            ['Botín con Cordones', 'botines', 510, null, ['Café', 'Miel'], 'Cuero nobuck', 'Resistente y abrigado, con suela antideslizante.', false],
            ['Zapatilla Urbana de Cuero', 'casuales', 350, null, ['Blanco', 'Negro', 'Gris'], 'Cuero legítimo', 'Minimalista y versátil. Combina con todo.', true],
            ['Náutico Clásico', 'casuales', 340, 390, ['Azul marino', 'Café'], 'Cuero legítimo', 'Cordones laterales y suela blanca. Estilo marinero de siempre.', false],
            ['Sandalia de Cuero', 'sandalias', 260, null, ['Café', 'Negro'], 'Cuero legítimo', 'Tiras ajustables y plantilla suave para el verano.', false],
        ];

        foreach ($productos as $idx => [$nombre, $catSlug, $precio, $antes, $cols, $material, $desc, $destacado]) {
            $slug = Str::slug($nombre);
            $product = Product::updateOrCreate(['slug' => $slug], [
                'category_id' => $cat[$catSlug]->id,
                'brand_id' => $radawes->id,
                'name' => $nombre,
                'short_description' => $desc,
                'description' => "$desc\n\nHecho en Brasil con $material. Disponible en tallas 38 a 44. Garantía de 30 días contra defectos de fábrica.",
                'base_price' => $precio,
                'compare_at_price' => $antes,
                'material' => $material,
                'origin_country' => 'Brasil',
                'is_featured' => $destacado,
                'is_active' => true,
                'published_at' => now()->subDays(30 - $idx),
            ]);

            $this->images($product, $cols, $paleta);
            $this->variants($product, $cols, $tallas, $colores, $branch);
        }

        // Scooter: solo color, sin talla
        $scooter = Product::updateOrCreate(['slug' => 'scooter-150cc'], [
            'category_id' => $cat['scooters']->id,
            'brand_id' => $generic->id,
            'name' => 'Scooter 150cc Automática',
            'short_description' => 'Automática, económica y lista para la ciudad.',
            'description' => "Motor 150cc, transmisión automática, arranque eléctrico, freno de disco delantero. Incluye casco de regalo.\n\nConsulta financiamiento por WhatsApp.",
            'base_price' => 14500,
            'material' => null,
            'origin_country' => 'China',
            'is_featured' => true,
            'is_active' => true,
            'published_at' => now()->subDays(3),
        ]);
        $this->images($scooter, ['Rojo', 'Negro'], $paleta);
        foreach (['Rojo', 'Negro'] as $c) {
            $v = $scooter->variants()->updateOrCreate(['sku' => 'SCO-150-'.strtoupper(substr(Str::slug($c), 0, 3))]);
            $v->attributeValues()->sync([$colores[$c]->id]);
            Stock::updateOrCreate(['product_variant_id' => $v->id, 'branch_id' => $branch->id], ['quantity' => 0, 'min_quantity' => 1]);
        }

        // Accesorios: sin variantes de talla
        foreach ([['Cinturón de Cuero', 120, ['Negro', 'Café']], ['Billetera de Cuero', 90, ['Café', 'Negro']], ['Kit de Limpieza para Cuero', 45, ['Negro']]] as $k => [$nombre, $precio, $cols]) {
            $p = Product::updateOrCreate(['slug' => Str::slug($nombre)], [
                'category_id' => $accesorios->id,
                'brand_id' => $radawes->id,
                'name' => $nombre,
                'short_description' => 'Complemento perfecto para tus zapatos.',
                'description' => 'Cuero legítimo brasileño. Pregunta por combos con tu calzado.',
                'base_price' => $precio,
                'material' => 'Cuero legítimo',
                'origin_country' => 'Brasil',
                'is_active' => true,
                'published_at' => now()->subDays(10 + $k),
            ]);
            $this->images($p, $cols, $paleta);
            foreach ($cols as $c) {
                $v = $p->variants()->updateOrCreate(['sku' => strtoupper(substr(Str::slug($nombre), 0, 3)).'-'.strtoupper(substr(Str::slug($c), 0, 3))]);
                $v->attributeValues()->sync([$colores[$c]->id]);
                Stock::updateOrCreate(['product_variant_id' => $v->id, 'branch_id' => $branch->id], ['quantity' => 0, 'min_quantity' => 1]);
            }
        }
    }

    private function variants(Product $product, array $cols, array $tallas, array $colores, Branch $branch): void
    {
        $base = 'P'.str_pad((string) $product->id, 3, '0', STR_PAD_LEFT);
        foreach ($cols as $c) {
            foreach ($tallas as $t => $tallaVal) {
                $sku = $base.'-'.strtoupper(substr(Str::slug($c), 0, 3)).'-'.$t;
                $v = $product->variants()->updateOrCreate(['sku' => $sku], ['is_active' => true]);
                $v->attributeValues()->sync([$colores[$c]->id, $tallaVal->id]);
                Stock::updateOrCreate(
                    ['product_variant_id' => $v->id, 'branch_id' => $branch->id],
                    ['quantity' => 0, 'min_quantity' => 1]
                );
            }
        }
    }

    /** Genera imágenes SVG de marcador (reemplazar por fotos reales). */
    private function images(Product $product, array $cols, array $paleta): void
    {
        $dir = public_path('images/products');
        File::ensureDirectoryExists($dir);
        $product->images()->delete();

        foreach ($cols as $i => $c) {
            $hex = $paleta[$c] ?? '#333';
            $text = $c === 'Blanco' ? '#333' : '#fff';
            $file = "{$product->slug}-".Str::slug($c).'.svg';
            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$hex}"/><stop offset="1" stop-color="{$hex}" stop-opacity="0.65"/></linearGradient></defs>
  <rect width="800" height="800" fill="url(#g)"/>
  <circle cx="400" cy="380" r="190" fill="rgba(255,255,255,0.08)"/>
  <text x="400" y="420" font-family="Georgia, serif" font-size="46" font-weight="bold" fill="{$text}" text-anchor="middle">{$product->name}</text>
  <text x="400" y="475" font-family="Arial, sans-serif" font-size="30" fill="{$text}" fill-opacity="0.85" text-anchor="middle">{$c}</text>
  <text x="400" y="740" font-family="Arial, sans-serif" font-size="22" fill="{$text}" fill-opacity="0.6" text-anchor="middle">RadaWes · Hecho en Brasil</text>
</svg>
SVG;
            File::put("$dir/$file", $svg);
            $product->images()->create([
                'path' => "images/products/$file",
                'alt' => "{$product->name} {$c}",
                'sort_order' => $i,
                'is_primary' => $i === 0,
            ]);
        }
    }
}
