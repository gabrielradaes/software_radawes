<?php

namespace App\Assistant\Skills;

use App\Assistant\Parser;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Services\VariantBuilder;
use App\Support\Gmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Precios, activar/ocultar productos, traspasos, clases de calzado, usuarios del panel y comentarios. */
trait Admin
{
    // =================================================================== precio

    private function fillPrecio(Parser $p, ?string $ask): void
    {
        $price = $p->price();
        if ($price === null && preg_match('/\b(?:a|en|por|es|sera)\s+(?:bs\.?\s*)?(\d{2,6}(?:[.,]\d{1,2})?)\s*(?:bs|bolivianos)?\s*\.?$/', $p->a, $m)) {
            $price = (float) str_replace(',', '.', $m[1]);
        }
        if ($price === null && $ask === 'price') {
            $price = $p->firstNumber();
        }
        if ($price !== null) {
            $this->state['data']['price'] = $price;
        }
    }

    private function nextPrecio(Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        if (empty($d['price'])) {
            return $this->ask('price', $prefix."¿Cuál es el **nuevo precio** de *{$product->name}*? (ahora está en ".money($product->base_price).')');
        }

        return $this->confirmCard($prefix.'Voy a cambiar el precio:', [
            ['Producto', $product->name.($product->code ? " ({$product->code})" : '')],
            ['Precio', money($product->base_price).' → '.money($d['price'])],
        ]);
    }

    private function executePrecio(): array
    {
        $d = $this->state['data'];
        $product = Product::findOrFail($d['product_id']);
        $old = $product->base_price;
        $product->update(['base_price' => $d['price'], 'compare_at_price' => $product->compare_at_price && $product->compare_at_price <= $d['price'] ? null : $product->compare_at_price]);
        $this->state = [];

        return $this->reply("✅ *{$product->name}* ahora cuesta **".money($d['price']).'** (antes '.money($old).').',
            links: [['Ver producto', route('admin.productos.edit', $product)]], meta: ['action' => 'precio', 'product_id' => $product->id]);
    }

    // =================================================================== activar / ocultar

    private function fillEstadoProducto(Parser $p, ?string $ask): void
    {
        $this->state['data']['active'] = ! $p->says('/\b(desactiva|oculta|ya no|saca(?:lo|la)?)/');
    }

    private function nextEstadoProducto(Product $product, string $prefix): array
    {
        $on = $this->state['data']['active'];

        return $this->confirmCard($prefix.($on ? 'Voy a **mostrar** este producto en la página:' : 'Voy a **ocultar** este producto de la página (no se borra):'), [
            ['Producto', $product->name.($product->code ? " ({$product->code})" : '')],
            ['Estado', ($product->is_active ? 'Visible' : 'Oculto').' → '.($on ? 'Visible' : 'Oculto')],
        ]);
    }

    private function executeEstadoProducto(): array
    {
        $d = $this->state['data'];
        $product = Product::findOrFail($d['product_id']);
        $product->update(['is_active' => $d['active'], 'published_at' => $d['active'] ? ($product->published_at ?? now()) : $product->published_at]);
        $this->state = [];

        return $this->reply('✅ *'.$product->name.'* '.($d['active'] ? 'ya se muestra en la página.' : 'ya no se muestra en la página.'),
            links: [['Ver producto', route('admin.productos.edit', $product)]], meta: ['action' => 'estado_producto', 'product_id' => $product->id]);
    }

    // =================================================================== traspasos entre tiendas

    private function fillTraspaso(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        $ids = array_values(array_filter($p->branches(), 'is_int'));
        if (! $ids) {
            return;
        }
        $scoped = $this->user->scopedBranchId();
        if ($ask === 'from') {
            $d['from'] = $ids[0];
        } elseif ($ask === 'to') {
            $d['to'] = $ids[0];
        } elseif (count($ids) >= 2) {
            // Orden en la frase: "de Calacoto a El Alto"
            $pos = collect($ids)->mapWithKeys(fn ($id) => [$id => $this->branchPosition($p, Branch::find($id))])->sort()->keys()->all();
            [$d['from'], $d['to']] = [$pos[0], $pos[1]];
        } else {
            // Una sola tienda: "desde Calacoto" = origen; "a El Alto" = destino
            $at = $this->branchPosition($p, Branch::find($ids[0]));
            $before = $at === PHP_INT_MAX ? '' : substr($p->a, 0, $at);
            $isFrom = ! $scoped && preg_match('/\b(?:de|desde)\s+(?:la\s+)?(?:tienda\s+)?(?:de\s+)?$/', $before);
            $d[$isFrom ? 'from' : 'to'] = $ids[0];
        }
        if ($scoped) {
            $d['from'] = $scoped;
        }
    }

    private function branchPosition(Parser $p, Branch $b): int
    {
        foreach ([str_replace('-', ' ', $b->slug), Parser::fold($b->name), $b->slug === 'el-alto' ? 'alto' : null] as $n) {
            if ($n && ($at = strpos($p->a, $n)) !== false) {
                return $at;
            }
        }

        return PHP_INT_MAX;
    }

    private function nextTraspaso(Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        $hasTalla = $product->variants->contains(fn ($v) => $v->valueOf('talla'));
        $productColors = $product->variants->map(fn ($v) => $v->valueOf('color'))->filter()->unique()->values();
        if ($hasTalla && empty($d['tallas'])) {
            return $this->ask('tallas', $prefix."¿Qué **talla** de *{$product->name}*?");
        }
        if (empty($d['colors']) && $productColors->count() > 1) {
            return $this->ask('colors', $prefix.'¿Qué **color**? ('.$productColors->implode(', ').')');
        }
        if (empty($d['qty'])) {
            return $this->ask('qty', $prefix.'¿Cuántos pares paso?');
        }
        $names = Branch::active()->get()->map(fn ($b) => self::short($b))->implode(', ');
        if (empty($d['from'])) {
            return $this->ask('from', $prefix."¿**Desde** qué tienda? ({$names})");
        }
        if (empty($d['to']) || $d['to'] === $d['from']) {
            return $this->ask('to', $prefix."¿**A** qué tienda? ({$names})");
        }
        [$from, $to] = [Branch::find($d['from']), Branch::find($d['to'])];
        $colors = $d['colors'] ?? ($productColors->count() === 1 ? [$productColors->first()] : []);
        $rows = [];
        foreach ($this->matchVariants($product, $d['tallas'] ?? [], $colors) as $l) {
            $have = $l['variant'] ? $l['variant']->stockInBranch($from->id) : 0;
            if ($have < $d['qty']) {
                $this->state['step'] = 'collect';

                return $this->ask('qty', $prefix."No alcanza: en ".self::short($from)." hay **{$have}** de {$l['label']}. ¿Cuántos paso?");
            }
            $there = $l['variant']->stockInBranch($to->id);
            $rows[] = [$l['label'], self::short($from).": {$have} → ".($have - $d['qty']).' · '.self::short($to).": {$there} → ".($there + $d['qty'])];
        }

        return $this->confirmCard($prefix."Traspaso de **{$d['qty']}** par(es) de *{$product->name}* de **".self::short($from).'** a **'.self::short($to).'**:', $rows, ['Variante', 'Stock']);
    }

    private function executeTraspaso(): array
    {
        $d = $this->state['data'];
        $product = Product::with('variants.attributeValues.attribute')->findOrFail($d['product_id']);
        [$from, $to] = [Branch::findOrFail($d['from']), Branch::findOrFail($d['to'])];
        $productColors = $product->variants->map(fn ($v) => $v->valueOf('color'))->filter()->unique()->values();
        $colors = $d['colors'] ?? ($productColors->count() === 1 ? [$productColors->first()] : []);

        // Traspaso en 2 pasos: sale ahora del origen y entra cuando la otra tienda confirma la recepción
        $lines = collect($this->matchVariants($product, $d['tallas'] ?? [], $colors))
            ->map(fn ($l) => ['variant' => $l['variant'] ?? throw new \RuntimeException("No existe {$l['label']}."), 'quantity' => $d['qty']])->all();
        $tr = \App\Services\OpsService::sendTransfer($this->user, $from, $to, $lines, 'Desde el asistente');
        $this->state = [];

        return $this->reply("✅ Enviado {$tr->code}: {$tr->pairs} par(es) de *{$product->name}* van en camino de ".self::short($from).' a '.self::short($to).'. Entran a su stock cuando '.self::short($to).' confirme que los recibió.',
            links: [['Ver traspaso', route('admin.traspasos.show', $tr)]], meta: ['action' => 'traspaso', 'product_id' => $product->id, 'transfer_id' => $tr->id]);
    }

    // =================================================================== nueva clase de calzado

    private function fillNuevaClase(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        $name = $ask === 'name' ? trim($p->t, ' .') : $p->after('/\b(?:clase|categoria|subcategoria)\s+(?:de calzado\s+|de motos\s+)?(?:llamada\s+|que se llame\s+)?/');
        if ($name) {
            $d['name'] = Str::ucfirst(preg_replace('/\s+(?:para|en)\s+(?:calzado|motos)$/i', '', $name));
        }
        $d['parent'] = $p->says('/\bmotos?\b/') ? 'motos' : 'calzado';
    }

    private function nextNuevaClase(?Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        if (empty($d['name'])) {
            return $this->ask('name', $prefix.'¿Cómo se llama la nueva clase? (ej.: Pantuflas)');
        }
        if (Category::whereRaw('LOWER(name) = ?', [mb_strtolower($d['name'])])->exists()) {
            $this->state = [];

            return $this->reply("Ya existe la clase **{$d['name']}**.");
        }

        return $this->confirmCard($prefix.'Voy a crear esta clase:', [['Clase', $d['name']], ['Dentro de', ucfirst($d['parent'])]]);
    }

    private function executeNuevaClase(): array
    {
        $d = $this->state['data'];
        $parent = Category::where('slug', $d['parent'])->first();
        $slug = Str::slug($d['name']);
        $i = 2;
        while (Category::where('slug', $slug)->exists()) {
            $slug = Str::slug($d['name']).'-'.$i++;
        }
        $c = Category::create(['name' => $d['name'], 'slug' => $slug, 'parent_id' => $parent?->id, 'is_active' => true,
            'sort_order' => (int) Category::where('parent_id', $parent?->id)->max('sort_order') + 1]);
        $this->state = [];

        return $this->reply("✅ Creé la clase **{$c->name}**. Ya puedes registrar productos en ella.", links: [['Ver clases', route('admin.categorias.index')]], meta: ['action' => 'nueva_clase']);
    }

    // =================================================================== usuarios del panel

    private function fillNuevoUsuario(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        if ($role = $p->staffRole()) {
            $d['role'] = $role;
        }
        $name = $ask === 'name' ? Parser::title(trim(preg_replace('/\S+@\S+/', '', $p->t), ' .,'))
            : $p->nameAfter('/\b(?:vendedor|vendedora|supervisor|supervisora|administrador|administradora|usuario|usuaria|empleado|empleada)\s+(?:nuevo\s+|nueva\s+)?(?:se llama\s+|llamad[oa]\s+)?(?=[a-z]{3,})/');
        if ($name && ! preg_match('/^(?:para|en|de|con)\b/i', $name)) {
            $d['name'] = $name;
        }
        if ($email = $p->email()) {
            $d['email'] = Str::lower($email);
        }
        if ($b = array_values(array_filter($p->branches(), 'is_int'))) {
            $d['branch_id'] = $b[0];
        }
    }

    private function nextNuevoUsuario(?Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        if (empty($d['role'])) {
            return $this->ask('role', $prefix.'¿Qué rol tendrá? (vendedor, supervisor o administrador)');
        }
        if (empty($d['name'])) {
            return $this->ask('name', $prefix.'¿Cómo se llama? (nombre y apellido)');
        }
        if (empty($d['email']) || ! preg_match(Gmail::REGEX, $d['email'])) {
            $bad = ! empty($d['email']) ? "«{$d['email']}» no es Gmail. " : '';
            unset($this->state['data']['email']);

            return $this->ask('email', $prefix.$bad."¿Cuál es su **Gmail**? (ej.: juan.perez@gmail.com)");
        }
        if (User::where('email', $d['email'])->exists()) {
            $this->state = [];

            return $this->reply("Ya hay un usuario del panel con **{$d['email']}**.", links: [['Ver usuarios', route('admin.usuarios.index')]]);
        }
        if ($d['role'] !== 'admin' && empty($d['branch_id'])) {
            return $this->ask('branch', $prefix.'¿De qué **tienda**? ('.Branch::active()->get()->map(fn ($b) => self::short($b))->implode(', ').')');
        }
        $roleLabel = Role::where('name', $d['role'])->value('label');

        return $this->confirmCard($prefix.'Voy a crear este usuario del panel:', [
            ['Nombre', $d['name']], ['Gmail', $d['email']], ['Rol', $roleLabel],
            ['Tienda', $d['role'] === 'admin' ? 'Todas' : self::short(Branch::find($d['branch_id']))],
        ]);
    }

    private function executeNuevoUsuario(): array
    {
        $d = $this->state['data'];
        $u = User::create([
            'name' => $d['name'], 'email' => $d['email'], 'password' => Str::random(40),
            'role_id' => Role::where('name', $d['role'])->value('id'),
            'branch_id' => $d['role'] === 'admin' ? null : $d['branch_id'], 'is_active' => true,
        ]);
        $this->state = [];

        return $this->reply("✅ Creé a **{$u->name}**. Para entrar por primera vez: que abra el panel (".route('admin.login').') → «¿Olvidaste tu contraseña?» con su Gmail; le llegará un código para crear su contraseña.',
            links: [['Ver usuarios', route('admin.usuarios.index')]], meta: ['action' => 'nuevo_usuario', 'user_id' => $u->id]);
    }

    // =================================================================== comentarios

    private function nextComentarios(?Product $product, string $prefix): array
    {
        $comments = Comment::with('commentable')->whereNull('parent_id')->latest()->limit(8)->get();
        $this->state = [];
        if ($comments->isEmpty()) {
            return $this->reply('No hay comentarios todavía.', meta: ['action' => 'comentarios']);
        }
        $rows = $comments->map(fn ($c) => [$c->created_at->diffForHumans(), $c->author_name ?? 'Cliente', Str::limit($c->body, 70), $c->commentable?->title ?? $c->commentable?->name ?? '—'])->all();

        return $this->reply('Últimos comentarios:', table: [['Cuándo', 'Quién', 'Comentario', 'En'], $rows],
            links: [['Responder o moderar', route('admin.comentarios.index')]], meta: ['action' => 'comentarios']);
    }
}
