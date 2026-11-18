<?php

namespace App\Assistant;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\User;
use App\Services\VariantBuilder;
use App\Support\ImageUpload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Asistente del panel: conversación por pasos (pregunta lo que falta), resumen, confirmación y ejecución.
 * Todo respeta el rol del usuario con los MISMOS permisos del panel (products.manage, stock.adjust, posts.manage, stock.view).
 *
 * Estado de la conversación (se guarda en la sesión):
 *   intent  nuevo_producto | entrada | salida | publicar | consulta
 *   step    collect | confirm
 *   ask     dato que se está preguntando
 *   data    datos reunidos
 *   images  fotos adjuntas (rutas ya guardadas en public/uploads)
 */
class Assistant
{
    private const NEEDS = [
        'nuevo_producto' => 'products.manage',
        'entrada' => 'stock.adjust',
        'salida' => 'stock.adjust',
        'publicar' => 'posts.manage',
        'consulta' => 'stock.view',
    ];

    private const LABELS = [
        'nuevo_producto' => 'crear productos',
        'entrada' => 'registrar entradas de stock',
        'salida' => 'registrar salidas de stock',
        'publicar' => 'hacer publicaciones',
        'consulta' => 'consultar stock',
    ];

    public array $state;

    public function __construct(public User $user, ?array $state = null)
    {
        $this->state = $state ?? [];
    }

    // =================================================================== entrada

    /** @param string[] $imagePaths fotos ya guardadas que llegaron con este mensaje */
    public function handle(string $text, array $imagePaths = []): array
    {
        $text = trim($text);
        if ($imagePaths) {
            $this->state['images'] = array_merge($this->state['images'] ?? [], $imagePaths);
        }
        if ($text === '') {
            if (! $imagePaths) {
                return $this->reply('Escríbeme o háblame con el micrófono 🎤.');
            }
            $got = count($imagePaths) === 1 ? 'Recibí la foto. ' : 'Recibí '.count($imagePaths).' fotos. ';
            if (! empty($this->state['intent'])) {
                return $this->next($got);
            }

            return $this->reply($got.'¿Qué hago con '.(count($this->state['images']) > 1 ? 'ellas' : 'ella').'? Ej.: «nuevo botín Chelsea código CH-210…» o «publica el CH-210».');
        }

        $p = new Parser($text);
        $step = $this->state['step'] ?? null;

        if ($step && $p->isNo()) {
            return $this->cancel();
        }
        if ($step === 'confirm') {
            if ($p->isYes()) {
                return $this->execute();
            }
            $before = $this->state['data'];
            $this->fill($p, null);
            if ($this->state['data'] != $before) {
                return $this->next('Corregido. ');
            }

            return $this->reply('¿Lo guardo? Responde **sí** o **no**, o dime qué corregir (ej.: «el precio es 480»).', confirm: true);
        }

        $intent = $p->intent();
        $current = $this->state['intent'] ?? null;
        $interrupts = $intent && $intent !== $current && $intent !== 'consulta'
            && ($intent !== 'entrada' || ! $current);   // "agrega…" mientras se pregunta algo no es otra orden

        if ($step === 'collect' && $current && ! $interrupts) {
            $this->fill($p, $this->state['ask'] ?? null);

            return $this->next();
        }

        // Orden nueva (las fotos adjuntas se conservan)
        $images = $this->state['images'] ?? [];
        $this->state = ['images' => $images];

        if ($intent === 'ayuda') {
            return $this->help();
        }
        if (! $intent) {
            $intent = ($p->code() || $this->findProduct($p)['product'] ?? null) ? 'consulta' : null;
        }
        if (! $intent) {
            return $this->notUnderstood();
        }

        // "Llegó el botín X…" de un producto que no existe = producto nuevo
        if ($intent === 'entrada') {
            $found = $this->findProduct($p);
            if (! $found['product'] && ! $found['options'] && ($p->productName() || ($p->code() && ! Product::where('code', $p->code())->exists() && $p->price()))) {
                $intent = 'nuevo_producto';
            }
        }

        if (! $this->user->can(self::NEEDS[$intent])) {
            return $this->reply("Tu rol (**{$this->roleName()}**) no permite ".self::LABELS[$intent].'. '.$this->canDo());
        }

        $this->state += ['intent' => $intent, 'step' => 'collect', 'ask' => null, 'data' => []];
        $this->fill($p, null);

        return $this->next();
    }

    public function cancel(): array
    {
        foreach ($this->state['images'] ?? [] as $path) {
            if (! \App\Models\ProductImage::where('path', $path)->exists() && ! \App\Models\PostMedia::where('path', $path)->exists()) {
                ImageUpload::delete($path);
            }
        }
        $this->state = [];

        return $this->reply('Listo, lo cancelé. No se guardó nada. ¿Qué más necesitas?');
    }

    // =================================================================== reunir datos

    private function fill(Parser $p, ?string $ask): void
    {
        $intent = $this->state['intent'];
        $d = &$this->state['data'];

        // Producto existente (para todo menos producto nuevo)
        if ($intent !== 'nuevo_producto') {
            $found = $this->findProduct($p, $ask === 'product');
            if ($found['product']) {
                $d['product_id'] = $found['product']->id;
                unset($d['options']);
            } elseif ($found['options']) {
                $d['options'] = $found['options'];
            } elseif ($found['missing_code']) {
                $d['missing_code'] = $found['missing_code'];
            }
            if ($ask === 'product' && ! empty($d['options']) && preg_match('/^\D*(\d)\D*$/', $p->a, $m) && isset($d['options'][$m[1] - 1])) {
                $d['product_id'] = $d['options'][$m[1] - 1];
                unset($d['options']);
            }
            if ($intent === 'consulta' && ! isset($d['product_id']) && ($cat = $p->category())) {
                $d['category_id'] = $cat->id;
            }
        }

        if ($intent === 'nuevo_producto') {
            $name = $p->productName() ?? ($ask === 'name' ? Parser::title(trim($p->t, ' .,')) : null);
            if ($name) {
                $d['name'] = $name;
            }
            if ($cat = $p->category()) {
                $d['category_id'] = $cat->id;
            }
            $code = $p->code();
            if ($code === null && $ask === 'code') {
                $code = $p->says('/\b(no tiene|ninguno|sin|no hay)\b/') ? '' : $p->bareCode();
            }
            if ($code !== null) {
                $d['code'] = $code;
            }
            if (($price = $p->price() ?? ($ask === 'price' ? $p->firstNumber() : null)) !== null) {
                $d['price'] = $price;
            }
            if ($m = $p->material()) {
                $d['material'] = $m;
            }
            if (($q = $p->quantityEach() ?? ($ask === 'qty_each' ? $p->firstNumber() : null)) !== null) {
                $d['qty_each'] = (int) $q;
            }
            if ($p->wantsPublish()) {
                $d['publish'] = true;
            }
            if ($layout = $p->layout()) {
                $d['layout'] = $layout;
            }
        } else {
            $q = $p->quantity() ?? $p->quantityEach() ?? (in_array($ask, ['qty'], true) ? $p->firstNumber() : null);
            if ($q !== null && in_array($intent, ['entrada', 'salida'], true)) {
                $d['qty'] = (int) $q;
            }
            if ($intent === 'publicar' && ($layout = $p->layout())) {
                $d['layout'] = $layout;
            }
        }

        // Colores y tallas (respuestas cortas cuando se están preguntando)
        $colors = $p->colors();
        if (! $colors && $ask === 'colors') {
            $colors = $p->says('/\b(sin color|ninguno|no tiene|un solo color|unico)\b/') ? ['-'] : $p->bareList()->map(fn ($c) => Str::ucfirst($c))->all();
        }
        if ($colors) {
            $d['colors'] = $colors === ['-'] ? [] : $colors;
            $d['colors_set'] = true;
        }
        $tallas = $p->tallas() ?: (in_array($ask, ['tallas'], true) ? $p->bareTallas() : []);
        if (! $tallas && $ask === 'tallas' && $p->says('/\b(sin talla|ninguna|no tiene|talla unica)\b/')) {
            $d['tallas'] = [];
            $d['tallas_set'] = true;
        }
        if ($tallas) {
            $d['tallas'] = $tallas;
            $d['tallas_set'] = true;
        }

        // Tienda(s)
        $branches = $p->branches();
        if ($branches) {
            $d['branches'] = $branches;
        }
    }

    /** Pregunta lo que falta o arma el resumen para confirmar. */
    private function next(string $prefix = ''): array
    {
        $intent = $this->state['intent'];
        $d = $this->state['data'];
        $scoped = $this->user->scopedBranchId();

        // ---- producto existente
        if ($intent !== 'nuevo_producto') {
            if (! empty($d['options'])) {
                $list = Product::whereIn('id', $d['options'])->get()->values()
                    ->map(fn ($pr, $i) => ($i + 1).'. '.$pr->name.($pr->code ? " ({$pr->code})" : ''))->implode("\n");

                return $this->ask('product', $prefix."Encontré varios. ¿Cuál?\n".$list."\n\nResponde con el número o el código.");
            }
            if (empty($d['product_id']) && ! ($intent === 'consulta' && ! empty($d['category_id']))) {
                $msg = ! empty($d['missing_code']) ? "No encontré ningún producto con el código **{$d['missing_code']}**. " : '';
                unset($this->state['data']['missing_code']);

                return $this->ask('product', $prefix.$msg.'¿De qué producto? Dime el **código** o el **nombre**.');
            }
        }

        $product = isset($d['product_id']) ? Product::with('variants.attributeValues.attribute', 'category')->find($d['product_id']) : null;
        if ($product && $scoped && ! $product->branches()->where('branches.id', $scoped)->exists() && $intent !== 'consulta') {
            $this->state = [];

            return $this->reply("**{$product->name}** no se vende en tu tienda, así que no puedo hacer eso desde tu cuenta.");
        }

        return match ($intent) {
            'nuevo_producto' => $this->nextNew($prefix),
            'entrada', 'salida' => $this->nextMove($product, $prefix),
            'publicar' => $this->nextPublish($product, $prefix),
            'consulta' => $this->runQuery($product),
        };
    }

    // ------------------------------------------------------------------- producto nuevo

    private function nextNew(string $prefix): array
    {
        $d = $this->state['data'];
        $category = isset($d['category_id']) ? Category::find($d['category_id']) : null;

        if (empty($d['name'])) {
            return $this->ask('name', $prefix.'¿Cómo se llama el producto? (ej.: *Botín Chelsea*)');
        }
        if (! $category) {
            $classes = Category::whereNotNull('parent_id')->where('is_active', true)->orderBy('sort_order')->pluck('name')->implode(', ');

            return $this->ask('category', $prefix."¿De qué clase es **{$d['name']}**? ({$classes})");
        }
        if (! array_key_exists('code', $d)) {
            return $this->ask('code', $prefix.'¿Cuál es el **código** del modelo? (ej.: CH-210, o di «sin código»)');
        }
        if ($d['code'] !== '' && ($other = Product::where('code', $d['code'])->first())) {
            $this->state = [];

            return $this->reply("Ya existe un producto con el código **{$d['code']}**: *{$other->name}*. Si llegaron más pares, dime por ejemplo: «entrada de 3 pares del {$d['code']} talla 40 negro».");
        }
        if (! isset($d['price'])) {
            return $this->ask('price', $prefix.'¿Cuál es el **precio** de venta en Bs.?');
        }
        $isShoe = $category->root()->slug === 'calzado';
        if (empty($d['colors_set'])) {
            return $this->ask('colors', $prefix.'¿En qué **colores** llegó? (ej.: café y negro, o «sin color»)');
        }
        if ($isShoe && empty($d['tallas_set'])) {
            return $this->ask('tallas', $prefix.'¿Qué **tallas**? (ej.: del 38 al 42)');
        }
        if (! $this->user->scopedBranchId() && empty($d['branches'])) {
            return $this->ask('branches', $prefix.'¿En qué **tienda** se vende? ('.Branch::active()->get()->map(fn ($b) => self::short($b))->implode(', ').' o «todas»)');
        }
        if (! isset($d['qty_each'])) {
            $what = ($d['tallas'] ?? []) ? 'de cada talla'.(count($d['colors'] ?? []) > 1 ? ' y color' : '') : 'de cada color';

            return $this->ask('qty_each', $prefix."¿Cuántos pares llegaron {$what}? (puede ser 0)");
        }

        $variants = max(1, count($d['colors'] ?? [])) * max(1, count($d['tallas'] ?? []));
        $branches = $this->targetBranches($d);
        $rows = [
            ['Producto', $d['name']],
            ['Código', $d['code'] !== '' ? $d['code'] : '— (sin código)'],
            ['Clase', $category->name],
            ['Precio', 'Bs. '.number_format($d['price'], 2)],
            ['Material', $d['material'] ?? '—'],
            ['Colores', ($d['colors'] ?? []) ? implode(', ', $d['colors']) : '—'],
            ['Tallas', $this->tallasLabel($d['tallas'] ?? [])],
            ['Tiendas', $branches->map(fn ($b) => self::short($b))->implode(', ')],
            ['Stock inicial', $d['qty_each'] > 0 ? "{$d['qty_each']} par(es) × {$variants} variante(s) = ".($d['qty_each'] * $variants).' por tienda' : 'sin stock por ahora'],
            ['Fotos', count($this->state['images'] ?? []) ?: 'ninguna (puedes adjuntarlas ahora 📷)'],
            ['Publicar en novedades', ! empty($d['publish']) ? 'Sí'.(($d['layout'] ?? 'carousel') === 'split' ? ', fotos separadas' : ', en carrusel') : 'No'],
        ];

        return $this->confirmCard($prefix.'Esto voy a registrar:', $rows);
    }

    private function executeNew(): array
    {
        $d = $this->state['data'];
        $branches = $this->targetBranches($d);
        $category = Category::findOrFail($d['category_id']);

        $product = DB::transaction(function () use ($d, $branches, $category) {
            $product = Product::create([
                'category_id' => $category->id,
                'name' => $d['name'],
                'code' => $d['code'] !== '' ? $d['code'] : null,
                'slug' => $this->uniqueSlug($d['name']),
                'base_price' => $d['price'],
                'material' => $d['material'] ?? null,
                'origin_country' => 'Brasil',
                'currency' => 'BOB',
                'is_active' => true,
                'published_at' => now(),
            ]);
            $product->branches()->sync($branches->mapWithKeys(fn ($b) => [$b->id => ['is_active' => true]])->all());

            foreach (array_values($this->state['images'] ?? []) as $i => $path) {
                $product->images()->create(['path' => $path, 'alt' => $product->name, 'sort_order' => $i + 1, 'is_primary' => $i === 0]);
            }

            $colorIds = collect($d['colors'] ?? [])->map(fn ($c) => VariantBuilder::value('color', $c)->id)->all();
            $tallaIds = collect($d['tallas'] ?? [])->map(fn ($t) => VariantBuilder::value('talla', (string) $t)->id)->all();
            $variants = VariantBuilder::generate($product, $colorIds, $tallaIds);

            if ($d['qty_each'] > 0) {
                foreach ($variants as $v) {
                    foreach ($branches as $b) {
                        Stock::firstOrCreate(['product_variant_id' => $v->id, 'branch_id' => $b->id], ['quantity' => 0, 'min_quantity' => 1])
                            ->move('entrada', $d['qty_each'], $this->user->id, 'Alta desde el asistente');
                    }
                }
            }

            return $product;
        });

        $text = "✅ Listo, registré **{$product->name}**".($product->code ? " ({$product->code})" : '').' con '.$product->variants()->count().' variante(s).';
        $links = [
            ['Ver / editar producto', route('admin.productos.edit', $product)],
            ['Variantes y stock', route('admin.productos.variantes', $product)],
            ['Ver en la tienda ↗', route('products.show', ['branch' => $branches->first()->slug, 'product' => $product->slug])],
        ];
        if (! $product->images()->exists()) {
            $text .= ' Todavía no tiene fotos: adjúntalas en «Ver / editar producto».';
        }
        $meta = ['product_id' => $product->id];

        if (! empty($d['publish']) && $this->user->can('posts.manage')) {
            $posts = $this->createPosts($product, $d['layout'] ?? 'carousel', $branches->count() === 1 ? $branches->first()->id : null, []);
            $text .= ' También lo publiqué en novedades'.(count($posts) > 1 ? ' ('.count($posts).' publicaciones)' : '').'.';
            $meta['post_ids'] = array_map(fn ($p) => $p->id, $posts);
        }
        $this->state = [];

        return $this->reply($text, links: $links, meta: $meta + ['action' => 'nuevo_producto']);
    }

    // ------------------------------------------------------------------- entradas y salidas

    private function nextMove(Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        $intent = $this->state['intent'];
        $hasTalla = $product->variants->contains(fn ($v) => $v->valueOf('talla'));
        $productColors = $product->variants->map(fn ($v) => $v->valueOf('color'))->filter()->unique()->values();

        if ($hasTalla && empty($d['tallas'])) {
            return $this->ask('tallas', $prefix."¿Qué **talla** de *{$product->name}*? (ej.: 40, o del 38 al 42)");
        }
        if (empty($d['colors']) && $productColors->count() > 1) {
            return $this->ask('colors', $prefix.'¿Qué **color**? ('.$productColors->implode(', ').')');
        }
        if (empty($d['qty'])) {
            return $this->ask('qty', $prefix.'¿Cuántos pares '.($intent === 'entrada' ? 'entran' : 'salen').(count($d['tallas'] ?? []) > 1 ? ' de cada talla' : '').'?');
        }
        $branch = $this->singleBranch($d);
        if (! $branch && $product->branches()->count() === 1) {
            $branch = $product->branches()->first();
            $this->state['data']['branches'] = [$branch->id];
        }
        if (! $branch) {
            return $this->ask('branches', $prefix.'¿En qué **tienda**? ('.$product->branches->map(fn ($b) => self::short($b))->implode(', ').')');
        }

        $colors = $d['colors'] ?? ($productColors->count() === 1 ? [$productColors->first()] : []);
        $lines = $this->matchVariants($product, $d['tallas'] ?? [], $colors);
        $rows = [];
        $total = 0;
        foreach ($lines as $l) {
            $now = $l['variant'] ? $l['variant']->stockInBranch($branch->id) : 0;
            $after = $intent === 'entrada' ? $now + $d['qty'] : $now - $d['qty'];
            if ($intent === 'salida' && (! $l['variant'] || $after < 0)) {
                $this->state['step'] = 'collect';

                return $this->ask('qty', "No alcanza: de *{$product->name}* {$l['label']} hay **{$now}** en {$branch->name}. ¿Cuántos salen?");
            }
            $rows[] = [$l['label'].($l['variant'] ? '' : ' (nueva)'), "{$now} → {$after}"];
            $total += $d['qty'];
        }

        $verb = $intent === 'entrada' ? 'Entrada' : 'Salida';

        return $this->confirmCard($prefix."{$verb} de **{$total}** par(es) de *{$product->name}*".($product->code ? " ({$product->code})" : '')." en **{$branch->name}**:", $rows, ['Variante', 'Stock']);
    }

    private function executeMove(): array
    {
        $d = $this->state['data'];
        $intent = $this->state['intent'];
        $product = Product::with('variants.attributeValues.attribute')->findOrFail($d['product_id']);
        $branch = $this->singleBranch($d);
        $productColors = $product->variants->map(fn ($v) => $v->valueOf('color'))->filter()->unique()->values();
        $colors = $d['colors'] ?? ($productColors->count() === 1 ? [$productColors->first()] : []);

        $done = DB::transaction(function () use ($product, $d, $intent, $branch, $colors) {
            $lines = $this->matchVariants($product, $d['tallas'] ?? [], $colors);
            $out = [];
            foreach ($lines as $l) {
                $variant = $l['variant'];
                if (! $variant && $intent === 'entrada') {
                    $product->branches()->syncWithoutDetaching([$branch->id => ['is_active' => true]]);
                    $variant = VariantBuilder::generate($product, array_filter([$l['color'] ? VariantBuilder::value('color', $l['color'])->id : null]), array_filter([$l['talla'] ? VariantBuilder::value('talla', $l['talla'])->id : null]))->first();
                }
                $stock = Stock::firstOrCreate(['product_variant_id' => $variant->id, 'branch_id' => $branch->id], ['quantity' => 0, 'min_quantity' => 1]);
                if ($intent === 'salida' && $stock->quantity < $d['qty']) {
                    throw new \RuntimeException("No alcanza el stock de {$l['label']} (hay {$stock->quantity}).");
                }
                $stock->move($intent, $intent === 'entrada' ? $d['qty'] : -$d['qty'], $this->user->id, 'Desde el asistente');
                $out[] = [$l['label'], (string) $stock->quantity];
            }

            return $out;
        });
        $this->state = [];

        return $this->reply('✅ '.($intent === 'entrada' ? 'Entrada' : 'Salida')." registrada en {$branch->name}. Stock actual:", table: [['Variante', 'Stock'], $done],
            links: [['Ver kardex y stock', route('admin.stock.index', ['q' => $product->name])]], meta: ['action' => $intent, 'product_id' => $product->id]);
    }

    /** Variantes que coinciden con tallas × colores pedidos (null si no existe todavía). */
    private function matchVariants(Product $product, array $tallas, array $colors): array
    {
        $lines = [];
        foreach ($colors ?: [null] as $c) {
            foreach ($tallas ?: [null] as $t) {
                $variant = $product->variants->first(function (ProductVariant $v) use ($c, $t) {
                    return (! $t || $v->valueOf('talla') === (string) $t)
                        && (! $c || Parser::fold((string) $v->valueOf('color')) === Parser::fold($c));
                });
                $label = trim(($t ? "Talla {$t}" : '').($t && $c ? ' · ' : '').($c ?? ''));
                $lines[] = ['variant' => $variant, 'talla' => $t ? (string) $t : null, 'color' => $c, 'label' => $label ?: 'Única'];
            }
        }

        return $lines;
    }

    // ------------------------------------------------------------------- publicar

    private function nextPublish(Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        $images = count($this->state['images'] ?? []) ?: $product->images()->count();
        if ($images > 1 && empty($d['layout'])) {
            return $this->ask('layout', $prefix."Tengo {$images} fotos. ¿Las publico juntas en **carrusel** o **separadas**?");
        }
        $branch = $this->user->scopedBranchId() ? Branch::find($this->user->scopedBranchId()) : $this->singleBranch($d, allowAll: true);

        return $this->confirmCard($prefix.'Voy a publicar esto en novedades:', [
            ['Producto', $product->name.($product->code ? " ({$product->code})" : '')],
            ['Tienda', $branch?->name ?? 'Todas las tiendas'],
            ['Fotos', $images ? $images.(count($this->state['images'] ?? []) ? ' (las que adjuntaste)' : ' (del producto)').(($d['layout'] ?? 'carousel') === 'split' ? ', separadas' : ', en carrusel') : 'sin fotos (solo texto)'],
            ['Texto', $this->postBody($product)],
        ]);
    }

    private function executePublish(): array
    {
        $d = $this->state['data'];
        $product = Product::findOrFail($d['product_id']);
        $branchId = $this->user->scopedBranchId() ?? $this->singleBranch($d, allowAll: true)?->id;
        $posts = $this->createPosts($product, $d['layout'] ?? 'carousel', $branchId, $this->state['images'] ?? []);
        $this->state = [];
        $store = Branch::find($branchId) ?? Branch::active()->where('is_main', true)->first() ?? Branch::first();

        return $this->reply('✅ Publicado'.(count($posts) > 1 ? ' ('.count($posts).' publicaciones)' : '').'.', links: [
            ['Ver en novedades ↗', route('feed.show', ['branch' => $store->slug, 'post' => $posts[0]])],
            ['Editar publicación', route('admin.publicaciones.edit', $posts[0])],
        ], meta: ['action' => 'publicar', 'post_ids' => array_map(fn ($p) => $p->id, $posts)]);
    }

    /** @return Post[] */
    private function createPosts(Product $product, string $layout, ?int $branchId, array $images): array
    {
        $paths = $images ?: $product->images()->pluck('path')->all();
        $base = [
            'user_id' => $this->user->id, 'branch_id' => $branchId, 'product_id' => $product->id,
            'type' => $paths ? 'foto' : 'texto', 'title' => $product->name, 'body' => $this->postBody($product),
            'is_pinned' => false, 'published_at' => now(),
        ];
        $groups = $layout === 'split' && count($paths) > 1 ? array_map(fn ($p) => [$p], $paths) : [$paths];
        $posts = [];
        foreach ($groups as $i => $group) {
            $post = Post::create([...$base, 'published_at' => now()->addSeconds($i)]);
            foreach (array_values($group) as $j => $path) {
                $post->media()->create(['type' => 'imagen', 'path' => $path, 'sort_order' => $j + 1]);
            }
            $posts[] = $post;
        }

        return $posts;
    }

    private function postBody(Product $product): string
    {
        $product->loadMissing('variants.attributeValues.attribute');
        $tallas = $product->variants->map(fn ($v) => $v->valueOf('talla'))->filter()->unique()->sort()->values()->all();
        $colors = $product->variants->map(fn ($v) => $v->valueOf('color'))->filter()->unique()->values()->all();
        $parts = array_filter([
            $product->material ? "Material: {$product->material}" : null,
            $colors ? 'Colores: '.implode(', ', $colors) : null,
            $tallas ? 'Tallas: '.$this->tallasLabel($tallas) : null,
            'Precio: Bs. '.number_format((float) $product->base_price, 2),
        ]);

        return "{$product->name}".($product->code ? " · Cód. {$product->code}" : '')."\n".implode(' · ', $parts)."\n¡Escríbenos por WhatsApp para separar tu par!";
    }

    // ------------------------------------------------------------------- consultas

    private function runQuery(?Product $product): array
    {
        $d = $this->state['data'];
        $scoped = $this->user->scopedBranchId();
        $branchIds = $scoped ? [$scoped] : collect($d['branches'] ?? [])->reject(fn ($b) => $b === 'todas')->all();
        $branches = Branch::active()->when($branchIds, fn ($q) => $q->whereKey($branchIds))->orderByDesc('is_main')->get();
        $note = $scoped && ! empty($d['branches']) && $d['branches'] !== [$scoped] ? ' (solo puedes ver el stock de tu tienda)' : '';
        $this->state = [];

        if (! $product) {
            $category = Category::find($d['category_id']);
            $ids = $category->descendantIds();
            $products = Product::whereIn('category_id', $ids)->when($scoped, fn ($q) => $q->inBranch($scoped))->orderBy('name')->limit(25)->get();
            if ($products->isEmpty()) {
                return $this->reply("No hay productos de **{$category->name}**{$note}.", meta: ['action' => 'consulta']);
            }
            $rows = $products->map(fn ($pr) => [$pr->name.($pr->code ? " ({$pr->code})" : ''), ...$branches->map(fn ($b) => (string) $pr->stockInBranch($b->id))->all()])->all();

            return $this->reply("Stock de **{$category->name}**{$note}:", table: [['Producto', ...$branches->map(fn ($b) => self::short($b))->all()], $rows], meta: ['action' => 'consulta']);
        }

        $product->load('variants.attributeValues.attribute', 'variants.stocks');
        $tallas = $d['tallas'] ?? [];
        $colors = collect($d['colors'] ?? [])->map(fn ($c) => Parser::fold($c));
        $variants = $product->variants->filter(fn ($v) => (! $tallas || in_array($v->valueOf('talla'), $tallas, true))
            && ($colors->isEmpty() || $colors->contains(Parser::fold((string) $v->valueOf('color')))));

        $rows = [];
        $total = 0;
        foreach ($variants as $v) {
            $cells = [];
            $sum = 0;
            foreach ($branches as $b) {
                $q = (int) ($v->stocks->firstWhere('branch_id', $b->id)->quantity ?? 0);
                $cells[] = (string) $q;
                $sum += $q;
            }
            if ($sum > 0 || $tallas || $colors->isNotEmpty()) {
                $rows[] = [$v->label() ?: 'Única', ...$cells];
            }
            $total += $sum;
        }
        $filter = trim(($tallas ? 'talla '.implode(', ', $tallas).' ' : '').($colors->isNotEmpty() ? implode(', ', $d['colors']) : ''));
        $title = "*{$product->name}*".($product->code ? " ({$product->code})" : '').($filter ? " — {$filter}" : '');
        if (! $rows) {
            return $this->reply("No hay stock de {$title}{$note}. 😕", links: [['Ver producto', route('admin.productos.variantes', $product)]], meta: ['action' => 'consulta']);
        }

        return $this->reply("Hay **{$total}** par(es) de {$title}{$note}:", table: [['Variante', ...$branches->map(fn ($b) => self::short($b))->all()], $rows],
            links: $this->user->can('products.manage') ? [['Variantes y stock', route('admin.productos.variantes', $product)]] : [], meta: ['action' => 'consulta', 'product_id' => $product->id]);
    }

    // =================================================================== confirmar

    public function execute(): array
    {
        $intent = $this->state['intent'] ?? null;
        if (($this->state['step'] ?? null) !== 'confirm' || ! $intent) {
            return $this->reply('No hay nada pendiente de confirmar. ¿Qué necesitas?');
        }
        if (! $this->user->can(self::NEEDS[$intent])) {
            $this->state = [];

            return $this->reply('Tu rol no permite hacer eso.');
        }

        try {
            return match ($intent) {
                'nuevo_producto' => $this->executeNew(),
                'entrada', 'salida' => $this->executeMove(),
                'publicar' => $this->executePublish(),
            };
        } catch (\RuntimeException $e) {
            $this->state['step'] = 'collect';

            return $this->reply('No se pudo: '.$e->getMessage());
        }
    }

    // =================================================================== ayudas

    /** Busca un producto por código o por nombre dentro del texto. */
    public function findProduct(Parser $p, bool $answering = false): array
    {
        $scoped = $this->user->scopedBranchId();
        $base = Product::query()->when($scoped && $this->user->cannot('products.manage'), fn ($q) => $q->inBranch($scoped));
        $res = ['product' => null, 'options' => [], 'missing_code' => null];

        $code = $p->code();
        if ($code === null && $answering && preg_match('/\d/', $p->a) && ! preg_match('/\s/', trim($p->a))) {
            $code = Parser::normalizeCode($p->t);
        }
        if ($code) {
            $found = (clone $base)->get(['id', 'code'])->first(fn ($pr) => $pr->code && Parser::normalizeCode($pr->code) === $code);
            if ($found) {
                $res['product'] = Product::find($found->id);

                return $res;
            }
            $res['missing_code'] = $code;
        }

        $words = collect(preg_split('/[^a-z0-9]+/', $p->a))->filter(fn ($w) => mb_strlen($w) >= 3)->map(fn ($w) => Parser::singular($w))->unique();
        $generic = Category::pluck('name')->flatMap(fn ($n) => preg_split('/[^a-z0-9]+/', Parser::fold($n)))->filter(fn ($w) => mb_strlen($w) > 3)
            ->map(fn ($w) => Parser::singular($w))->merge(['zapato', 'calzado', 'cuero', 'producto', 'modelo', 'talla', 'color', 'negro', 'cafe'])->unique();

        $scored = (clone $base)->get(['id', 'name'])->map(function ($pr) use ($words, $generic) {
            $nameWords = collect(preg_split('/[^a-z0-9]+/', Parser::fold($pr->name)))->filter(fn ($w) => mb_strlen($w) >= 3)->map(fn ($w) => Parser::singular($w))->unique();
            $hits = $nameWords->intersect($words);
            $distinct = $hits->diff($generic)->count();

            return ['id' => $pr->id, 'score' => $hits->count() / max(1, $nameWords->count()), 'distinct' => $distinct];
        })->filter(fn ($s) => $s['distinct'] > 0 && $s['score'] >= 0.5)->sortByDesc('score')->values();

        if ($scored->isEmpty()) {
            return $res;
        }
        $top = $scored->where('score', $scored->first()['score']);
        if ($top->count() === 1) {
            $res['product'] = Product::find($top->first()['id']);
            $res['missing_code'] = null;
        } else {
            $res['options'] = $top->take(6)->pluck('id')->all();
        }

        return $res;
    }

    private function targetBranches(array $d): Collection
    {
        if ($scoped = $this->user->scopedBranchId()) {
            return Branch::whereKey($scoped)->get();
        }
        $sel = $d['branches'] ?? [];

        return in_array('todas', $sel, true) ? Branch::active()->orderByDesc('is_main')->get() : Branch::whereKey($sel)->get();
    }

    private function singleBranch(array $d, bool $allowAll = false): ?Branch
    {
        if ($scoped = $this->user->scopedBranchId()) {
            return Branch::find($scoped);
        }
        $sel = collect($d['branches'] ?? [])->reject(fn ($b) => $b === 'todas');

        return $sel->count() === 1 ? Branch::find($sel->first()) : null;
    }

    private function tallasLabel(array $tallas): string
    {
        if (! $tallas) {
            return '—';
        }
        $n = array_map('intval', $tallas);
        sort($n);

        return count($n) > 2 && end($n) - $n[0] === count($n) - 1 ? "{$n[0]} al ".end($n) : implode(', ', $n);
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

    /** "RadaWes · Calacoto" → "Calacoto" */
    public static function short(Branch $b): string
    {
        return trim(Str::afterLast($b->name, '·')) ?: $b->name;
    }

    private function roleName(): string
    {
        return $this->user->role?->label ?? 'usuario';
    }

    private function canDo(): string
    {
        $can = collect(self::NEEDS)->filter(fn ($perm) => $this->user->can($perm))->keys()
            ->map(fn ($i) => self::LABELS[$i])->unique()->values();

        return $can->isEmpty() ? '' : 'Sí puedo ayudarte a '.$can->join(', ', ' y ').'.';
    }

    private function examples(): array
    {
        $ex = [];
        if ($this->user->can('stock.view')) {
            $ex[] = '«¿Cuántos CH-210 talla 40 hay?» · «stock de botines»';
        }
        if ($this->user->can('products.manage')) {
            $ex[] = '«Nuevo botín Chelsea código CH-210, colores café y negro, tallas del 38 al 42, 3 pares de cada uno, precio 450'.($this->user->scopedBranchId() ? '' : ', en Calacoto').'»';
        }
        if ($this->user->can('stock.adjust')) {
            $ex[] = '«Llegaron 5 pares del CH-210 talla 40 negro» · «Vendí 1 CH-210 talla 41 café»';
        }
        if ($this->user->can('posts.manage')) {
            $ex[] = '«Publica el CH-210 en carrusel» (puedes adjuntar fotos 📷)';
        }

        return $ex;
    }

    private function help(): array
    {
        $this->state = ['images' => $this->state['images'] ?? []];

        return $this->reply("Soy tu asistente 🤖. Háblame con el micrófono 🎤 o escríbeme. Puedo:\n• ".implode("\n• ", $this->examples())
            ."\n\nSi falta un dato te lo pregunto, y **nada se guarda sin que confirmes**. Para cancelar di «cancelar».");
    }

    private function notUnderstood(): array
    {
        return $this->reply("No te entendí 🤔. Prueba así:\n• ".implode("\n• ", $this->examples()));
    }

    // =================================================================== respuestas

    private function ask(string $field, string $text): array
    {
        $this->state['step'] = 'collect';
        $this->state['ask'] = $field;

        return $this->reply($text);
    }

    private function confirmCard(string $text, array $rows, array $headers = ['Dato', 'Valor']): array
    {
        $this->state['step'] = 'confirm';
        $this->state['ask'] = null;

        return $this->reply($text."\n\n¿Lo guardo? Puedes decir **sí**, **no** o corregir algo.", table: [$headers, $rows], confirm: true);
    }

    private function reply(string $text, ?array $table = null, array $links = [], bool $confirm = false, array $meta = []): array
    {
        return [
            'text' => $text,
            'table' => $table,
            'links' => $links,
            'confirm' => $confirm,
            'intent' => $this->state['intent'] ?? ($meta['action'] ?? null),
            'meta' => $meta,
        ];
    }
}
