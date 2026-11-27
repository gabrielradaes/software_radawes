<?php

namespace App\Http\Controllers\Admin;

use App\Assistant\Parser;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Services\VariantBuilder;
use App\Support\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ingreso manual de calzado al stock, en una sola pantalla (lo mismo que hace el asistente, pero a mano):
 *  1. Elegir el modelo (buscar por código o nombre) o crear uno nuevo ahí mismo.
 *  2. Marcar colores y tallas y escribir cuántos pares llegaron de cada uno.
 *  3. Guardar: crea las variantes que falten y suma los pares (entrada en el kardex).
 */
class StockEntryController extends Controller
{
    public function create(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $branches = Branch::active()->when($scoped, fn ($q) => $q->whereKey($scoped))->orderByDesc('is_main')->get();
        $classes = Category::where('is_active', true)->with('parent')->orderBy('parent_id')->orderBy('sort_order')->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->parent ? $c->name : $c->name.' (general)', 'shoe' => ($c->root()->slug ?? null) === 'calzado'])
            ->sortBy(fn ($c) => [$c['shoe'] ? 0 : 1, $c['name']])->values();
        $known = \App\Models\AttributeValue::whereHas('attribute', fn ($q) => $q->where('slug', 'color'))->orderBy('sort_order')->pluck('value');
        $preset = $request->filled('producto') ? Product::find((int) $request->query('producto')) : null;

        return view('admin.stock.entry', [
            'branches' => $branches,
            'scoped' => $scoped,
            'classes' => $classes,
            'knownColors' => $known->merge(collect(Parser::COLORS)->map(fn ($c) => Str::ucfirst(['cafe' => 'café', 'marron' => 'marrón'][$c] ?? $c)))
                ->unique(fn ($c) => Parser::fold($c))->values(),
            'preset' => $preset ? ['id' => $preset->id, 'name' => $preset->name, 'code' => $preset->code] : null,
            // Prellenado desde el asistente (modelos dictados): código, marca, color y talla
            'prefill' => array_filter([
                'code' => $preset ? null : mb_substr((string) $request->query('codigo'), 0, 40),
                'brand' => mb_substr((string) $request->query('marca'), 0, 60),
                'color' => mb_substr((string) $request->query('color'), 0, 40),
                'talla' => preg_match('/^[0-9A-Za-z.]{1,6}$/', (string) $request->query('talla')) ? (string) $request->query('talla') : null,
            ]),
            'brands' => \App\Models\Brand::orderBy('name')->pluck('name'),
            'done' => $request->filled('hecho') ? Product::find((int) $request->query('hecho')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $scoped = $user->scopedBranchId();
        $isNew = ! $request->filled('product_id');
        abort_if($isNew && $user->cannot('products.manage'), 403, 'Tu usuario no puede crear modelos nuevos: pídeselo al supervisor.');
        if ($isNew && $request->filled('code')) {
            $request->merge(['code' => Parser::normalizeCode($request->input('code'))]);
        }

        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'branch_id' => [$scoped ? 'nullable' : 'required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'name' => [$isNew && ! $request->filled('code') ? 'required' : 'nullable', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('products', 'code')],
            'category_id' => [$isNew ? 'required' : 'nullable', 'integer', 'exists:categories,id'],
            'price' => [$isNew ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'material' => ['nullable', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'show_web' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:200'],
            'colors' => ['nullable', 'array', 'max:20'],
            'colors.*' => ['nullable', 'string', 'max:40'],
            'tallas' => ['nullable', 'array', 'max:40'],
            'tallas.*' => ['nullable', 'string', 'max:10', 'regex:/^[0-9A-Za-z.\/½ -]+$/u'],
            'q' => ['nullable', 'array'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => [ImageUpload::rule(6144)],
        ], [
            'code.unique' => 'Ya hay un modelo con ese código: búscalo arriba en «Ya existe» en vez de crearlo de nuevo.',
            'name.required' => 'Escribe el nombre o el código del modelo.',
            'category_id.required' => 'Elige la clase (botín, mocasín…).',
            'price.required' => 'Escribe el precio de venta.',
            'branch_id.required' => 'Elige la tienda donde llegaron los pares.',
            'tallas.*.regex' => 'Las tallas solo pueden tener números o letras (ej.: 40, 41.5, S, M).',
        ], ['name' => 'nombre', 'code' => 'código', 'category_id' => 'clase', 'price' => 'precio', 'cost' => 'costo', 'branch_id' => 'tienda']);

        $branch = Branch::findOrFail($scoped ?? $data['branch_id']);

        // Colores y tallas tal como se marcaron (sin repetidos ni vacíos); "sin color" / "sin talla" = una sola fila o columna
        $colors = collect($data['colors'] ?? [])->map(fn ($c) => trim((string) $c));
        $tallas = collect($data['tallas'] ?? [])->map(fn ($t) => str_replace(',', '.', trim((string) $t)))->filter(fn ($t) => $t !== '')->unique()->values();
        if (! $isNew && $colors->filter()->isEmpty() && ProductVariant::where('product_id', $data['product_id'])
            ->whereHas('attributeValues.attribute', fn ($q) => $q->where('slug', 'color'))->exists()) {
            throw ValidationException::withMessages(['colors' => 'Este modelo tiene colores: marca de qué color llegaron los pares.']);
        }
        $grid = $request->input('q', []);
        $lines = [];
        foreach ($colors->isEmpty() ? collect(['']) : $colors as $ci => $color) {
            foreach ($tallas->isEmpty() ? collect(['']) : $tallas as $talla) {
                $raw = $grid[$ci][$talla === '' ? '_' : $talla] ?? null;
                if ($raw === null || $raw === '') {
                    continue;
                }
                if (! preg_match('/^\d{1,4}$/', trim((string) $raw))) {
                    throw ValidationException::withMessages(['q' => 'Las cantidades deben ser números enteros (0, 1, 2…).']);
                }
                $lines[] = ['color' => $color !== '' ? Str::ucfirst($color) : null, 'talla' => $talla !== '' ? $talla : null, 'qty' => (int) $raw];
            }
        }
        $total = array_sum(array_column($lines, 'qty'));
        if (! $isNew && $total < 1) {
            throw ValidationException::withMessages(['q' => 'Escribe cuántos pares llegaron en al menos una talla.']);
        }
        if ($isNew && $colors->filter()->isEmpty() && $tallas->isEmpty() && $total < 1) {
            throw ValidationException::withMessages(['q' => 'Marca al menos un color o una talla del modelo nuevo.']);
        }

        $product = DB::transaction(function () use ($data, $isNew, $branch, $user, $request, $colors, $tallas, $lines) {
            if ($isNew) {
                // Sin nombre: marca + código («Demócrata 596101-001»)
                $name = filled($data['name'] ?? null) ? Parser::title($data['name']) : trim(($data['brand'] ?? '') ?: 'Modelo').' '.$data['code'];
                $product = Product::create([
                    'category_id' => $data['category_id'],
                    'brand_id' => ! empty($data['brand']) ? \App\Models\Brand::firstOrCreate(['slug' => Str::slug($data['brand'])], ['name' => trim($data['brand'])])->id : null,
                    'name' => $name,
                    'code' => ($data['code'] ?? '') !== '' ? $data['code'] : null,
                    'slug' => $this->uniqueSlug($name),
                    'base_price' => $data['price'],
                    'cost' => $data['cost'] ?? null,
                    'material' => $data['material'] ?? null,
                    'origin_country' => 'Brasil',
                    'currency' => 'BOB',
                    'is_active' => true,
                    'published_at' => $request->boolean('show_web', true) ? now() : null,
                ]);
                $order = 0;
                foreach ($request->file('images', []) as $file) {
                    $product->images()->create(['path' => ImageUpload::store($file, 'products'), 'alt' => $product->name, 'sort_order' => ++$order, 'is_primary' => $order === 1]);
                }
            } else {
                $product = Product::lockForUpdate()->findOrFail($data['product_id']);
                if (isset($data['cost']) && $data['cost'] !== null && $data['cost'] !== '') {
                    $product->update(['cost' => $data['cost']]);
                }
            }
            // Que se venda en esta tienda (si llegó aquí, aquí se vende)
            $product->branches()->syncWithoutDetaching([$branch->id => ['is_active' => true]]);

            // Variantes: el modelo nuevo lleva todas las combinaciones marcadas; uno existente, solo las que recibieron pares
            $wanted = $isNew
                ? collect($colors->isEmpty() ? [null] : $colors->all())->crossJoin($tallas->isEmpty() ? [null] : $tallas->all())->map(fn ($p) => ['color' => $p[0] !== null && $p[0] !== '' ? Str::ucfirst($p[0]) : null, 'talla' => $p[1]])
                : collect($lines)->where('qty', '>', 0);
            foreach ($wanted->groupBy(fn ($l) => (string) $l['color']) as $color => $group) {
                $colorIds = $color !== '' ? [VariantBuilder::value('color', $color)->id] : [];
                $tallaIds = $group->pluck('talla')->filter()->map(fn ($t) => VariantBuilder::value('talla', (string) $t)->id)->values()->all();
                if ($colorIds || $tallaIds) {
                    VariantBuilder::generate($product, $colorIds, $tallaIds);
                } elseif (! $product->variants()->exists()) {
                    $product->variants()->create(['sku' => 'P'.str_pad((string) $product->id, 3, '0', STR_PAD_LEFT).'-U']);   // sin color ni talla
                }
            }

            $variants = $product->variants()->with('attributeValues.attribute')->get();
            $reason = 'Ingreso manual'.(! empty($data['note']) ? ': '.$data['note'] : '');
            foreach ($lines as $l) {
                if ($l['qty'] < 1) {
                    continue;
                }
                $variant = $this->variantFor($variants, $l['color'], $l['talla']);
                if (! $variant) {
                    throw ValidationException::withMessages(['q' => "No pude preparar la variante {$l['color']} {$l['talla']}. Inténtalo otra vez."]);
                }
                Stock::firstOrCreate(['product_variant_id' => $variant->id, 'branch_id' => $branch->id], ['quantity' => 0, 'min_quantity' => 1])
                    ->move('entrada', $l['qty'], $user->id, mb_substr($reason, 0, 255));
            }

            return $product;
        });

        $msg = $total > 0
            ? "✅ Listo: entraron {$total} par(es) de {$product->name}".($product->code ? " ({$product->code})" : '')." en {$branch->name}."
            : "✅ Modelo {$product->name} creado (sin pares por ahora).";

        return upload_aware(redirect()->route('admin.stock.ingresar', ['hecho' => $product->id, 'sucursal' => $branch->id])->with('status', $msg));
    }

    /** La variante del producto con ese color y esa talla (comparando sin tildes ni mayúsculas). */
    private function variantFor($variants, ?string $color, ?string $talla): ?ProductVariant
    {
        return $variants->first(fn (ProductVariant $v) => Parser::fold((string) $v->valueOf('color')) === Parser::fold((string) $color)
            && (string) $v->valueOf('talla') === (string) $talla);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'producto';
        $slug = $base;
        $i = 2;
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "$base-".$i++;
        }

        return $slug;
    }
}
