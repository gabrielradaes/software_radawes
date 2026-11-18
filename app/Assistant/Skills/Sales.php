<?php

namespace App\Assistant\Skills;

use App\Assistant\Parser;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\WhatsappLead;
use App\Services\SaleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Ventas con cliente, reportes de ventas y consultas de WhatsApp (leads). */
trait Sales
{
    // =================================================================== venta

    private function fillVenta(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        if ($ask === 'customer' && $p->says('/^\W*(?:no|ninguno|nadie|sin cliente|no tiene|no quiso|anonimo|cliente de paso)\W*$/')) {
            $d['customer_none'] = true;

            return;
        }
        if ($ask === 'customer_name') {
            $d['new_name'] = \App\Assistant\Parser::title(trim(preg_replace('/[\d+]+/', '', $p->t), ' .,'));

            return;
        }
        if (in_array($ask, [null, 'customer'], true) || $p->phone()) {
            $this->resolveCustomer($p, $ask);
            if (! empty($d['new_phone']) && ($name = $p->personName() ?? $p->nameAfter('/\b(?:a|al|para)\s+(?:la senora|el senor|don|dona)?\s*(?=[a-z]{3,})/'))) {
                $d['new_name'] = $name;
            }
        }
        if ($pay = $p->payment()) {
            $d['payment'] = $pay;
        }
        if (($price = $p->price()) !== null) {
            $d['unit_price'] = $price;
        }
        if ($p->says('/\bpor whatsapp\b/')) {
            $d['channel'] = 'whatsapp';
        }
    }

    private function nextVenta(Product $product, string $prefix): array
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
        $this->state['data']['qty'] ??= 1;
        $d['qty'] = $this->state['data']['qty'];
        $branch = $this->saleBranch($product, $d);
        if (! $branch) {
            return $this->ask('branches', $prefix.'¿En qué **tienda** se vendió? ('.$product->branches->map(fn ($b) => self::short($b))->implode(', ').')');
        }

        $colors = $d['colors'] ?? ($productColors->count() === 1 ? [$productColors->first()] : []);
        $lines = $this->matchVariants($product, $d['tallas'] ?? [], $colors);
        $rows = [];
        $total = 0;
        foreach ($lines as $l) {
            $have = $l['variant'] ? $l['variant']->stockInBranch($branch->id) : 0;
            if (! $l['variant'] || $have < $d['qty']) {
                $this->state['step'] = 'collect';

                return $this->ask('qty', $prefix."No alcanza: de *{$product->name}* {$l['label']} hay **{$have}** en ".self::short($branch).'. ¿Cuántos se vendieron?');
            }
            $price = $d['unit_price'] ?? $l['variant']->effectivePrice();
            $total += $price * $d['qty'];
            $rows[] = [$l['label'], "{$d['qty']} × ".money($price)];
        }

        // Cliente
        $customer = ! empty($d['customer_id']) ? Customer::find($d['customer_id']) : null;
        if (! $customer && empty($d['customer_none'])) {
            if (! empty($d['customer_options'])) {
                return $this->askCustomer($prefix);
            }
            if (! empty($d['new_phone']) && empty($d['new_name'])) {
                return $this->ask('customer_name', $prefix."El celular **{$d['new_phone']}** no está registrado. ¿Cómo se llama el cliente?");
            }
            if (empty($d['new_phone'])) {
                return $this->ask('customer', $prefix.'¿A nombre de quién? Dime el **nombre** o **celular** del cliente, o «sin cliente».');
            }
        }


        return $this->confirmCard($prefix."Venta de *{$product->name}*".($product->code ? " ({$product->code})" : '').' en **'.self::short($branch).'**:', [
            ...$rows,
            ['Total', money($total)],
            ['Cliente', $customer ? $this->customerLabel($customer) : (! empty($d['new_phone']) ? "{$d['new_name']} ({$d['new_phone']}) — nuevo" : '— (sin cliente)')],
            ['Pago', SaleService::PAYMENTS[$d['payment'] ?? 'efectivo']],
        ], ['Detalle', 'Valor']);
    }

    private function saleBranch(Product $product, array $d): ?Branch
    {
        $branch = $this->singleBranch($d);
        if (! $branch && $product->branches()->count() === 1) {
            $branch = $product->branches()->first();
            $this->state['data']['branches'] = [$branch->id];
        }

        return $branch;
    }

    private function executeVenta(): array
    {
        $d = $this->state['data'];
        $product = Product::with('variants.attributeValues.attribute')->findOrFail($d['product_id']);
        $branch = $this->singleBranch($d) ?? Branch::find($d['branches'][0] ?? null);
        $productColors = $product->variants->map(fn ($v) => $v->valueOf('color'))->filter()->unique()->values();
        $colors = $d['colors'] ?? ($productColors->count() === 1 ? [$productColors->first()] : []);

        $sale = DB::transaction(function () use ($d, $product, $branch, $colors) {
            $customer = ! empty($d['customer_id']) ? Customer::find($d['customer_id']) : null;
            if (! $customer && ! empty($d['new_phone'])) {
                $customer = Customer::firstOrCreate(['phone' => $d['new_phone']], ['name' => $d['new_name'] ?: 'Cliente '.$d['new_phone'], 'source' => 'tienda', 'is_active' => true, 'branch_id' => $branch->id]);
            }
            $lines = collect($this->matchVariants($product, $d['tallas'] ?? [], $colors))
                ->map(fn ($l) => ['variant' => $l['variant'] ?? throw new \RuntimeException("No existe la variante {$l['label']}."), 'quantity' => $d['qty'], 'unit_price' => $d['unit_price'] ?? null])->all();

            return SaleService::register($this->user, $branch, $customer, $lines, $d['payment'] ?? 'efectivo', $d['channel'] ?? 'tienda');
        });
        $this->state = [];

        $links = [['Ver venta', route('admin.ventas.show', $sale)]];
        if ($sale->customer) {
            $links[] = ['Ficha del cliente', route('admin.clientes.show', $sale->customer)];
        }

        return $this->reply("✅ Venta **{$sale->code}** registrada por **".money($sale->total).'**'.($sale->customer ? " a {$sale->customer->name}" : '').'. El stock ya se descontó.',
            links: $links, meta: ['action' => 'venta', 'sale_id' => $sale->id]);
    }

    // =================================================================== reportes

    private function fillReporte(Parser $p, ?string $ask): void
    {
        [$from, $to, $label] = $p->period();
        $this->state['data']['period'] = [$from->toIso8601String(), $to->toIso8601String(), $label];
        if ($b = $p->branches()) {
            $this->state['data']['branches'] = $b;
        }
    }

    private function nextReporte(?Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        [$from, $to, $label] = [Carbon::parse($d['period'][0]), Carbon::parse($d['period'][1]), $d['period'][2]];
        $scoped = $this->user->scopedBranchId();
        $branchIds = $scoped ? [$scoped] : collect($d['branches'] ?? [])->filter(fn ($b) => is_int($b))->all();
        $this->state = [];

        $sales = Sale::where('status', 'pagada')->whereBetween('sold_at', [$from, $to])->when($branchIds, fn ($q) => $q->whereIn('branch_id', $branchIds));
        $total = (float) (clone $sales)->sum('total');
        $count = (clone $sales)->count();
        $pairs = (int) SaleItem::whereIn('sale_id', (clone $sales)->select('id'))->sum('quantity');
        $where = $branchIds ? ' en '.Branch::whereKey($branchIds)->get()->map(fn ($b) => self::short($b))->implode(', ') : '';

        if (! $count) {
            return $this->reply("No hay ventas registradas {$label}{$where}.", links: [['Ver ventas', route('admin.ventas.index')]], meta: ['action' => 'reporte']);
        }
        $top = SaleItem::join('product_variants', 'product_variants.id', '=', 'sale_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereIn('sale_items.sale_id', (clone $sales)->select('id'))
            ->groupBy('products.id', 'products.name')->selectRaw('products.name, sum(sale_items.quantity) as pares, sum(sale_items.line_total) as monto')
            ->orderByDesc('pares')->limit(5)->get();
        $rows = $top->map(fn ($r) => [$r->name, (string) $r->pares, money($r->monto)])->all();

        $byBranch = '';
        if (! $branchIds) {
            $byBranch = "\n".Sale::where('status', 'pagada')->whereBetween('sold_at', [$from, $to])->with('branch')->get()->groupBy('branch_id')
                ->map(fn ($g) => '• '.self::short($g->first()->branch).': '.money($g->sum('total')))->implode("\n");
        }

        return $this->reply("Ventas {$label}{$where}: **".money($total)."** en {$count} venta(s), {$pairs} par(es).{$byBranch}\n\nLo más vendido:",
            table: [['Producto', 'Pares', 'Monto'], $rows], links: [['Ver ventas', route('admin.ventas.index')]], meta: ['action' => 'reporte']);
    }

    // =================================================================== leads de WhatsApp

    private function nextLeads(?Product $product, string $prefix): array
    {
        $scoped = $this->user->scopedBranchId();
        $leads = WhatsappLead::with('customer', 'product', 'branch')->whereIn('status', ['nuevo', 'contactado'])
            ->when($scoped, fn ($q) => $q->where('branch_id', $scoped))->latest()->limit(10)->get();
        $this->state = [];
        if ($leads->isEmpty()) {
            return $this->reply('No hay consultas de WhatsApp pendientes. 👌', links: [['Ver leads', route('admin.leads.index')]], meta: ['action' => 'leads']);
        }
        $rows = $leads->map(fn ($l) => [$l->created_at->diffForHumans(), $l->customer?->name ?? 'Visitante', $l->product?->name ?? 'Consulta general', $l->status])->all();

        return $this->reply("Hay **{$leads->count()}** consulta(s) de WhatsApp por atender:", table: [['Cuándo', 'Quién', 'Producto', 'Estado'], $rows],
            links: [['Ver leads', route('admin.leads.index')]], meta: ['action' => 'leads']);
    }
}
