<?php

namespace App\Assistant\Skills;

use App\Assistant\Parser;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Post;
use App\Models\Product;
use App\Services\OpsService;
use App\Services\SaleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Apartados, caja y publicaciones libres (sin producto) desde el asistente. */
trait Ops
{
    // =================================================================== apartar un par

    private function fillApartar(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        if ($ask === 'customer_name') {
            $d['new_name'] = Parser::title(trim(preg_replace('/[\d+]+/', '', $p->t), ' .,'));

            return;
        }
        if (in_array($ask, [null, 'customer'], true) || $p->phone()) {
            $this->resolveCustomer($p, $ask);
            if (! empty($d['new_phone']) && ($name = $p->personName() ?? $p->nameAfter('/\b(?:a|al|para)\s+(?:la senora|el senor|don|dona)?\s*(?=[a-z]{3,})/'))) {
                $d['new_name'] = $name;
            }
        }
        if (preg_match('/\b(?:adelanto|a cuenta|deja(?:ndo)?|dejo|dejó|pago|pagando|abono|abona)\s*(?:de\s*)?(?:bs\.?\s*)?(\d{1,6}(?:[.,]\d{1,2})?)/', $p->a, $m)) {
            $d['deposit'] = (float) str_replace(',', '.', $m[1]);
        } elseif ($ask === 'deposit') {
            $d['deposit'] = $p->saysNone() || $p->says('/\b(nada|sin adelanto|cero)\b/') ? 0.0 : ($p->firstNumber() ?? null);
            if ($d['deposit'] === null) {
                unset($d['deposit']);
            }
        }
        if ($pay = $p->payment()) {
            $d['payment'] = $pay;
        }
        if (preg_match('/\b(?:por|para|durante)\s+(\d{1,2})\s+dias?\b/', $p->a, $m)) {
            $d['days'] = min(30, max(1, (int) $m[1]));
        } elseif (($when = $p->when()) && $when['at']->isFuture()) {
            $d['until'] = $when['at']->toDateString();
        }
    }

    private function nextApartar(Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        $hasTalla = $product->variants->contains(fn ($v) => $v->valueOf('talla'));
        $productColors = $product->variants->map(fn ($v) => $v->valueOf('color'))->filter()->unique()->values();
        if ($hasTalla && empty($d['tallas'])) {
            return $this->ask('tallas', $prefix."¿Qué **talla** de *{$product->name}* aparto?");
        }
        if (empty($d['colors']) && $productColors->count() > 1) {
            return $this->ask('colors', $prefix.'¿Qué **color**? ('.$productColors->implode(', ').')');
        }
        $this->state['data']['qty'] ??= 1;
        $d['qty'] = $this->state['data']['qty'];
        $branch = $this->saleBranch($product, $d);
        if (! $branch) {
            return $this->ask('branches', $prefix.'¿En qué **tienda** se aparta? ('.$product->branches->map(fn ($b) => self::short($b))->implode(', ').')');
        }

        $colors = $d['colors'] ?? ($productColors->count() === 1 ? [$productColors->first()] : []);
        $rows = [];
        $total = 0;
        foreach ($this->matchVariants($product, $d['tallas'] ?? [], $colors) as $l) {
            $free = $l['variant'] ? (\App\Models\Stock::where('product_variant_id', $l['variant']->id)->where('branch_id', $branch->id)->first()?->available() ?? 0) : 0;
            if ($free < $d['qty']) {
                $this->state = [];

                return $this->reply("No hay *{$product->name}* {$l['label']} libre para apartar en ".self::short($branch)." (disponible: {$free}).", links: $this->manualLinks('consulta'));
            }
            $price = $l['variant']->effectivePrice();
            $total += $price * $d['qty'];
            $rows[] = [$l['label'], "{$d['qty']} × ".money($price)];
        }

        $customer = ! empty($d['customer_id']) ? Customer::find($d['customer_id']) : null;
        if (! $customer) {
            if (! empty($d['customer_options'])) {
                return $this->askCustomer($prefix);
            }
            if (! empty($d['new_phone']) && empty($d['new_name'])) {
                return $this->ask('customer_name', $prefix."El celular **{$d['new_phone']}** no está registrado. ¿Cómo se llama el cliente?");
            }
            if (empty($d['new_phone'])) {
                return $this->askCustomer($prefix, '¿Para quién lo aparto? Dime el **nombre** o **celular** del cliente.');
            }
        }
        if (! isset($d['deposit'])) {
            return $this->ask('deposit', $prefix.'¿Cuánto deja de **adelanto**? (di «nada» si no deja)');
        }
        if ($d['deposit'] > $total) {
            unset($this->state['data']['deposit']);

            return $this->ask('deposit', $prefix.'El adelanto no puede ser mayor que el total ('.money($total).'). ¿Cuánto deja?');
        }
        $until = $this->reservationUntil($d);

        return $this->confirmCard($prefix."Apartado de *{$product->name}*".($product->code ? " ({$product->code})" : '').' en **'.self::short($branch).'**:', [
            ...$rows,
            ['Total', money($total)],
            ['Adelanto', money($d['deposit']).($d['deposit'] > 0 ? ' · '.SaleService::PAYMENTS[$d['payment'] ?? 'efectivo'] : '')],
            ['Saldo', money($total - $d['deposit'])],
            ['Cliente', $customer ? $this->customerLabel($customer) : "{$d['new_name']} ({$d['new_phone']}) — nuevo"],
            ['Guardado hasta', $until->translatedFormat('l j \d\e F')],
        ], ['Detalle', 'Valor']);
    }

    private function reservationUntil(array $d): Carbon
    {
        return ! empty($d['until']) ? Carbon::parse($d['until'])->endOfDay() : now()->addDays($d['days'] ?? 3)->endOfDay();
    }

    private function executeApartar(): array
    {
        $d = $this->state['data'];
        $product = Product::with('variants.attributeValues.attribute')->findOrFail($d['product_id']);
        $branch = $this->singleBranch($d) ?? Branch::find($d['branches'][0] ?? null);
        $productColors = $product->variants->map(fn ($v) => $v->valueOf('color'))->filter()->unique()->values();
        $colors = $d['colors'] ?? ($productColors->count() === 1 ? [$productColors->first()] : []);

        $customer = ! empty($d['customer_id']) ? Customer::find($d['customer_id'])
            : Customer::firstOrCreate(['phone' => $d['new_phone']], ['name' => $d['new_name'] ?: 'Cliente '.$d['new_phone'], 'source' => 'tienda', 'is_active' => true, 'branch_id' => $branch->id]);
        $lines = collect($this->matchVariants($product, $d['tallas'] ?? [], $colors))
            ->map(fn ($l) => ['variant' => $l['variant'] ?? throw new \RuntimeException("No existe la variante {$l['label']}."), 'quantity' => $d['qty']])->all();
        $res = OpsService::reserve($this->user, $branch, $customer, $lines, (float) $d['deposit'], $d['payment'] ?? 'efectivo', $this->reservationUntil($d), 'Desde el asistente');
        $this->state = [];

        return $this->reply("📌 Apartado **{$res->code}** para {$customer->name}: los pares quedan reservados hasta el ".$res->expires_at->translatedFormat('l j \d\e F').'. Saldo por cobrar: **'.money($res->total - $res->deposit).'**.',
            links: [['Ver apartado', route('admin.apartados.show', $res)]], meta: ['action' => 'apartar', 'reservation_id' => $res->id]);
    }

    // =================================================================== caja

    private function fillCaja(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        if ($b = array_values(array_filter($p->branches(), 'is_int'))) {
            $d['branches'] = $b;
        }
        $d['op'] ??= match (true) {
            $p->says('/\b(abr[ei]r?|abre|abrimos|apertura|inicia(?:r)?)\b/') => 'abrir',
            $p->says('/\b(cierra|cerrar|cerramos|cierre)\b/') => 'cerrar',
            $p->says('/\b(gaste|gasto|gastamos|pague|pagamos|saque|sacamos|egreso|retiro|retire)\b/') => 'egreso',
            $p->says('/\b(ingreso|ingresa|meti|metimos|entro|entraron|deposito)\b/') && $p->firstNumber() !== null => 'ingreso',
            default => 'ver',
        };
        $n = $p->price() ?? $p->firstNumber();
        if ($n !== null && $n >= 0 && in_array($ask, [null, 'amount'], true)) {
            $d['amount'] = (float) $n;
        }
        if (in_array($d['op'], ['egreso', 'ingreso'], true) && ! isset($d['note'])) {
            $note = trim(preg_replace('/\b(?:caja|de la caja|en la caja|bs\.?|bolivianos|\d+(?:[.,]\d+)?)\b/u', ' ', $p->t));
            $note = trim(preg_replace('/^(?:gast[eé]|pagu[eé]|saqu[eé]|ingres[oó]|met[ií])\s+(?:en|de|el|la|para)?\s*/iu', '', preg_replace('/\s+/', ' ', $note)), ' .,');
            if (mb_strlen($note) >= 3 && $ask !== 'amount') {
                $d['note'] = Str::ucfirst(mb_substr($note, 0, 200));
            } elseif ($ask === 'note' && $p->t !== '') {
                $d['note'] = Str::ucfirst(mb_substr(trim($p->t), 0, 200));
            }
        }
        if ($pay = $p->payment()) {
            $d['payment'] = $pay;
        }
    }

    private function nextCaja(?Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        $branch = $this->singleBranch($d) ?? ($this->user->branch_id ? Branch::find($this->user->branch_id) : null);
        if (! $branch) {
            return $this->ask('branches', $prefix.'¿La caja de qué **tienda**? ('.Branch::active()->get()->map(fn ($b) => self::short($b))->implode(', ').')');
        }
        $open = CashSession::openFor($branch->id);
        $where = self::short($branch);
        $link = [['Abrir la caja', route('admin.caja', ['sucursal' => $branch->id])]];

        if ($d['op'] === 'ver') {
            $this->state = [];
            if (! $open) {
                return $this->reply("La caja de **{$where}** está cerrada. Di «abre la caja con 200» para abrirla.", links: $link, meta: ['action' => 'caja']);
            }
            $rows = collect($open->totalsByPayment())->map(fn ($v, $k) => [SaleService::PAYMENTS[$k] ?? Str::ucfirst($k), money($v['total']), (string) $v['count']])->values()->all();

            return $this->reply("Caja de **{$where}** abierta desde las {$open->opened_at->format('H:i')}. Efectivo que debería haber: **".money($open->expectedCash()).'**.',
                table: $rows ? [['Medio', 'Neto del turno', 'Ventas'], $rows] : null, links: $link, meta: ['action' => 'caja']);
        }
        if ($d['op'] === 'abrir' && $open) {
            $this->state = [];

            return $this->reply("La caja de **{$where}** ya está abierta (desde las {$open->opened_at->format('H:i')}).", links: $link, meta: ['action' => 'caja']);
        }
        if ($d['op'] !== 'abrir' && ! $open) {
            $this->state = [];

            return $this->reply("La caja de **{$where}** está cerrada. Primero ábrela: «abre la caja con 200».", links: $link, meta: ['action' => 'caja']);
        }
        if (! isset($d['amount'])) {
            return $this->ask('amount', $prefix.match ($d['op']) {
                'abrir' => '¿Con cuánto **efectivo** abres la caja? (Bs.)',
                'cerrar' => '¿Cuánto **efectivo contaste** en la caja? (Bs.)',
                default => '¿De cuánto fue? (Bs.)',
            });
        }
        if (in_array($d['op'], ['egreso', 'ingreso'], true) && empty($d['note'])) {
            return $this->ask('note', $prefix.'¿Para qué fue? (ej.: pasaje, almuerzo, bolsas)');
        }

        $rows = match ($d['op']) {
            'abrir' => [['Acción', 'Abrir caja'], ['Efectivo inicial', money($d['amount'])]],
            'cerrar' => [['Acción', 'Cerrar caja'], ['Efectivo esperado', money($open->expectedCash())], ['Efectivo contado', money($d['amount'])], ['Diferencia', money($d['amount'] - $open->expectedCash())]],
            default => [['Acción', $d['op'] === 'egreso' ? 'Gasto (sale dinero)' : 'Ingreso (entra dinero)'], ['Monto', money($d['amount'])], ['Motivo', $d['note']], ['Medio', SaleService::PAYMENTS[$d['payment'] ?? 'efectivo']]],
        };

        return $this->confirmCard($prefix."Caja de **{$where}**:", $rows);
    }

    private function executeCaja(): array
    {
        $d = $this->state['data'];
        $branch = $this->singleBranch($d) ?? Branch::findOrFail($this->user->branch_id);
        if ($this->user->scopedBranchId() && $this->user->scopedBranchId() !== $branch->id) {
            throw new \RuntimeException('Es la caja de otra tienda.');
        }
        $open = CashSession::openFor($branch->id);
        $this->state = [];
        $link = [['Ver la caja', route('admin.caja', ['sucursal' => $branch->id])]];

        if ($d['op'] === 'abrir') {
            if ($open) {
                throw new \RuntimeException('La caja ya está abierta.');
            }
            CashSession::create(['branch_id' => $branch->id, 'opened_by' => $this->user->id, 'opened_at' => now(), 'opening_amount' => $d['amount']]);

            return $this->reply('💵 Caja abierta con '.money($d['amount']).'. Las ventas de hoy se suman a este turno.', links: $link, meta: ['action' => 'caja']);
        }
        if (! $open) {
            throw new \RuntimeException('La caja está cerrada.');
        }
        if ($d['op'] === 'cerrar') {
            $expected = $open->expectedCash();
            $open->update(['closed_at' => now(), 'closed_by' => $this->user->id, 'expected_cash' => $expected, 'counted_cash' => $d['amount'],
                'difference' => $d['amount'] - $expected, 'totals' => $open->totalsByPayment(), 'notes' => 'Cerrada desde el asistente']);
            $diff = $d['amount'] - $expected;

            return $this->reply(abs($diff) < 0.01 ? '💵 Caja cerrada: cuadra perfecto ✔' : '💵 Caja cerrada con una diferencia de **'.money($diff).'**.', links: $link, meta: ['action' => 'caja']);
        }
        $open->movements()->create(['user_id' => $this->user->id, 'type' => $d['op'], 'payment_method' => $d['payment'] ?? 'efectivo',
            'amount' => $d['op'] === 'egreso' ? -$d['amount'] : $d['amount'], 'note' => $d['note']]);

        return $this->reply('💵 '.($d['op'] === 'egreso' ? 'Gasto' : 'Ingreso').' de '.money($d['amount'])." registrado: {$d['note']}.", links: $link, meta: ['action' => 'caja']);
    }

    // =================================================================== publicar sin producto

    /** Color de fondo dicho ("fondo rojo", "en color azul", "degradado atardecer"). */
    private function postBg(Parser $p): ?string
    {
        if (! $p->says('/\b(fondo|color de fondo|en color|de color|degradado)\b/')) {
            return null;
        }
        foreach (Post::GRADIENTS as $key => [$label]) {
            if ($p->says('/\b'.preg_quote(Parser::fold($label), '/').'\b/')) {
                return $key;
            }
        }
        foreach (Post::SOLIDS as $hex => $label) {
            if ($p->says('/\bfondo\s+(?:de\s+)?(?:color\s+)?'.preg_quote(Parser::fold($label), '/').'\b|\b(?:en|de) color\s+'.preg_quote(Parser::fold($label), '/').'\b/')) {
                return $hex;
            }
        }
        $more = ['amarillo' => '#ca8a04', 'blanco' => '#f5f5f4', 'cafe' => '#78350f', 'marron' => '#78350f', 'celeste' => '#0ea5e9', 'gris' => '#4b5563', 'dorado' => '#a16207'];
        foreach ($more as $word => $hex) {
            if ($p->says('/\bfondo\s+(?:de\s+)?(?:color\s+)?'.$word.'\b|\b(?:en|de) color\s+'.$word.'\b/')) {
                return $hex;
            }
        }

        return null;
    }

    /** Texto de la publicación: lo que viene después de «publica…:» (sin las palabras de la orden). */
    private function postText(Parser $p): ?string
    {
        $t = $p->t;
        if (preg_match('/[:«"“](.+)$/su', $t, $m)) {
            $t = $m[1];
        } else {
            $t = preg_replace('/^\W*(?:por favor\s+)?(?:publica(?:r|lo|la)?|postea(?:r)?|sube|subir|haz una publicaci[oó]n|crea una publicaci[oó]n|nueva publicaci[oó]n)\b\s*(?:esto|que|diciendo|con el texto|el texto)?\s*/iu', '', $t);
        }
        $t = preg_replace('/\s*,?\s*(?:con\s+)?(?:fondo|en color|de color|degradado)\s+(?:de\s+)?(?:color\s+)?[\p{L}]+\s*$/u', '', trim($t));
        $t = preg_replace('/\s*,?\s*(?:en|para)\s+(?:la tienda\s+(?:de\s+)?)?(?:calacoto|comercio|el alto|todas las tiendas)\s*$/iu', '', $t);
        $t = trim($t, " \t\n\"”»«.,");

        return mb_strlen($t) >= 3 && ! Parser::isGenericName($t) ? Str::ucfirst(mb_substr($t, 0, 1500)) : null;
    }

    private function nextFreePost(string $prefix): array
    {
        $d = $this->state['data'];
        $images = count($this->state['images'] ?? []);
        if (empty($d['post_text']) && ! $images) {
            return $this->ask('post_text', $prefix.'¿Qué publico? Dime el **texto** (ej.: «Llegó colección nueva, fondo rojo») o el **código** del producto. También puedes adjuntar fotos 📷.');
        }
        if ($images > 1 && empty($d['layout'])) {
            return $this->ask('layout', $prefix."Tengo {$images} fotos. ¿Las publico juntas en **carrusel** o **separadas**?");
        }
        $branch = $this->user->scopedBranchId() ? Branch::find($this->user->scopedBranchId()) : $this->singleBranch($d, allowAll: true);
        $bgLabel = ! empty($d['bg']) ? (Post::SOLIDS[$d['bg']] ?? (Post::GRADIENTS[$d['bg']][0] ?? $d['bg'])) : 'Normal (blanco)';

        return $this->confirmCard($prefix.'Voy a publicar esto en novedades:', [
            ['Texto', $d['post_text'] ?? '—'],
            ['Fotos', $images ? $images.' (las que adjuntaste)' : 'sin fotos'],
            ['Fondo', $images ? 'Normal (con fotos)' : $bgLabel],
            ['Tienda', $branch?->name ?? 'Todas las tiendas'],
        ]);
    }

    private function executeFreePost(): array
    {
        $d = $this->state['data'];
        $branchId = $this->user->scopedBranchId() ?? $this->singleBranch($d, allowAll: true)?->id;
        $paths = array_values($this->state['images'] ?? []);
        $text = (string) ($d['post_text'] ?? '');
        $title = Str::limit(Str::before($text, "\n"), 80, '…');
        $groups = ($d['layout'] ?? 'carousel') === 'split' && count($paths) > 1 ? array_map(fn ($p) => [$p], $paths) : [$paths];
        $posts = [];
        foreach ($groups as $i => $group) {
            $post = Post::create(['user_id' => $this->user->id, 'branch_id' => $branchId, 'type' => $group ? 'foto' : 'texto',
                'title' => $title !== '' ? $title : null, 'body' => mb_strlen($text) > 80 ? $text : null, 'bg' => $group ? null : ($d['bg'] ?? null),
                'is_pinned' => false, 'published_at' => now()->addSeconds($i)]);
            foreach ($group as $j => $path) {
                $post->media()->create(['type' => 'imagen', 'path' => $path, 'sort_order' => $j + 1]);
            }
            $posts[] = $post;
        }
        $this->state = [];
        $store = Branch::find($branchId) ?? Branch::active()->where('is_main', true)->first() ?? Branch::first();

        return $this->reply('✅ Publicado'.(count($posts) > 1 ? ' ('.count($posts).' publicaciones)' : '').'.', links: [
            ['Ver en novedades ↗', route('feed.show', ['branch' => $store->slug, 'post' => $posts[0]])],
            ['Editar publicación', route('admin.publicaciones.edit', $posts[0])],
        ], meta: ['action' => 'publicar', 'post_ids' => array_map(fn ($p) => $p->id, $posts)]);
    }
}
