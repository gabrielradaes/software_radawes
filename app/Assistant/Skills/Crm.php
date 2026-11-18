<?php

namespace App\Assistant\Skills;

use App\Assistant\Parser;
use App\Models\Customer;
use App\Models\CustomerTask;
use App\Models\Product;
use App\Models\Tag;
use App\Support\Gmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Clientes: registrar, ficha, notas, seguimientos (recordatorios), etapa y etiquetas. */
trait Crm
{
    // =================================================================== buscar clientes

    /** Busca un cliente por celular, Gmail o nombre dentro del texto. */
    public function findCustomer(Parser $p, array $exclude = []): array
    {
        $res = ['customer' => null, 'options' => [], 'phone' => $p->phone()];
        if ($res['phone'] && ($c = Customer::where('phone', $res['phone'])->first())) {
            return ['customer' => $c] + $res;
        }
        if (($email = $p->email()) && ($c = Customer::where('email', Str::lower($email))->first())) {
            return ['customer' => $c] + $res;
        }

        $stop = collect(Parser::COLORS)->flatMap(fn ($c) => explode(' ', $c))
            ->merge(['cliente', 'clienta', 'talla', 'tallas', 'pares', 'venta', 'vendi', 'nota', 'llamar', 'manana', 'hoy', 'senor', 'senora',
                'don', 'dona', 'para', 'como', 'que', 'con', 'del', 'los', 'las', 'una', 'uno', 'por', 'whatsapp', 'efectivo', 'tarjeta',
                'ficha', 'datos', 'quien', 'recuerdame', 'seguimiento', 'frecuente', 'interesado', 'prospecto', 'perdido', 'etiqueta',
                'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo', 'calacoto', 'comercio', 'alto', 'tienda', 'precio',
                'codigo', 'quiere', 'dijo', 'marca', 'como', 'vip', 'mayorista', 'llame', 'hable', 'escribi', 'avisar', 'avisale'])
            ->merge($exclude)->map(fn ($w) => Parser::fold($w))->unique();
        $words = collect(preg_split('/[^a-z]+/', $p->a))->filter(fn ($w) => mb_strlen($w) >= 3)->diff($stop)->unique()->values();
        if ($words->isEmpty()) {
            return $res;
        }

        $candidates = Customer::where(function ($q) use ($words) {
            foreach ($words as $w) {
                $q->orWhere('name', 'like', '%'.$w.'%');
            }
        })->limit(300)->get(['id', 'name']);

        $scored = $candidates->map(function ($c) use ($words) {
            $name = collect(preg_split('/[^a-z]+/', Parser::fold($c->name)))->filter(fn ($w) => mb_strlen($w) >= 3)->unique();
            $hits = $name->intersect($words)->count();

            return ['id' => $c->id, 'hits' => $hits, 'ratio' => $hits / max(1, $name->count())];
        })->filter(fn ($s) => $s['hits'] > 0)->sortByDesc(fn ($s) => $s['hits'] * 10 + $s['ratio'])->values();

        if ($scored->isEmpty()) {
            return $res;
        }
        $best = $scored->first();
        $top = $scored->filter(fn ($s) => $s['hits'] === $best['hits'] && $s['ratio'] === $best['ratio']);
        if ($top->count() === 1) {
            $res['customer'] = Customer::find($best['id']);
        } else {
            $res['options'] = $top->take(6)->pluck('id')->all();
        }

        return $res;
    }

    /** Guarda en data el cliente encontrado (o las opciones / el celular nuevo). */
    private function resolveCustomer(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        if ($ask === 'customer' && ! empty($d['customer_options']) && preg_match('/^\D*(\d)\D*$/', $p->a, $m) && isset($d['customer_options'][$m[1] - 1])) {
            $d['customer_id'] = $d['customer_options'][$m[1] - 1];
            unset($d['customer_options']);

            return;
        }
        $exclude = isset($d['product_id']) ? preg_split('/[^a-z]+/', Parser::fold((string) Product::find($d['product_id'])?->name)) : [];
        $found = $this->findCustomer($p, $exclude);
        if ($found['customer']) {
            $d['customer_id'] = $found['customer']->id;
            unset($d['customer_options'], $d['new_phone']);
        } elseif ($found['options']) {
            $d['customer_options'] = $found['options'];
        } elseif ($found['phone']) {
            $d['new_phone'] = $found['phone'];
        }
    }

    private function askCustomer(string $prefix, string $question = '¿De qué cliente? Dime su **nombre** o **celular**.'): array
    {
        $d = $this->state['data'];
        if (! empty($d['customer_options'])) {
            $list = Customer::whereIn('id', $d['customer_options'])->get()->values()
                ->map(fn ($c, $i) => ($i + 1).'. '.$c->name.($c->phone ? " ({$c->phone})" : ''))->implode("\n");

            return $this->ask('customer', $prefix."Hay varios clientes con ese nombre. ¿Cuál?\n{$list}\n\nResponde con el número o el celular.");
        }

        return $this->ask('customer', $prefix.$question);
    }

    private function customerLabel(?Customer $c): string
    {
        return $c ? $c->name.($c->phone ? " ({$c->phone})" : '') : '—';
    }

    // =================================================================== nuevo cliente

    private function fillNuevoCliente(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        $name = $p->personName() ?? ($ask === 'name' ? Parser::title(trim(preg_replace('/\s+\+?\d[\d\s]*$/', '', $p->t), ' .,')) : null);
        if ($name) {
            $d['name'] = $name;
        }
        if ($phone = $p->phone()) {
            $d['phone'] = $phone;
        } elseif ($ask === 'phone' && $p->says('/\b(no tiene|sin celular|ninguno|no sabe|despues)\b/')) {
            $d['phone'] = '';
        }
        if ($email = $p->email()) {
            $email = Str::lower($email);
            if (preg_match(Gmail::REGEX, $email)) {
                $d['email'] = $email;
                unset($d['bad_email']);
            } else {
                $d['bad_email'] = $email;
            }
        }
        foreach (['document' => $p->document(), 'city' => $p->city(), 'source' => $p->source()] as $k => $v) {
            if ($v) {
                $d[$k] = $v;
            }
        }
        if ($size = $p->usualSize() ?? ($p->tallas()[0] ?? null)) {
            $d['preferred_size'] = $size;
        }
        if ($tags = $p->tagNames()) {
            $d['tags'] = $tags;
        }
    }

    private function nextNuevoCliente(?Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        if (empty($d['name'])) {
            return $this->ask('name', $prefix.'¿Cómo se llama el cliente? (nombre y apellido)');
        }
        if (! array_key_exists('phone', $d)) {
            return $this->ask('phone', $prefix."¿Cuál es el **celular** de {$d['name']}? (o «no tiene»)");
        }
        $dup = ($d['phone'] ? Customer::where('phone', $d['phone'])->first() : null) ?? (! empty($d['email']) ? Customer::where('email', $d['email'])->first() : null);
        if ($dup) {
            $this->state = [];

            return $this->reply("Ese cliente ya está registrado: **{$dup->name}**.", links: [['Ver ficha', route('admin.clientes.show', $dup)]]);
        }

        return $this->confirmCard($prefix.'Voy a registrar este cliente:', array_values(array_filter([
            ['Nombre', $d['name']],
            ['Celular', $d['phone'] ?: '—'],
            ['Gmail', $d['email'] ?? (isset($d['bad_email']) ? "— (no guardo {$d['bad_email']}: solo se aceptan Gmail)" : '—')],
            isset($d['document']) ? ['CI / NIT', $d['document']] : null,
            isset($d['city']) ? ['Ciudad', $d['city']] : null,
            isset($d['preferred_size']) ? ['Talla habitual', $d['preferred_size']] : null,
            isset($d['source']) ? ['Nos conoció por', Customer::SOURCES[$d['source']]] : null,
            ! empty($d['tags']) ? ['Etiquetas', implode(', ', array_map(fn ($t) => mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1), $d['tags']))] : null,
        ])));
    }

    private function executeNuevoCliente(): array
    {
        $d = $this->state['data'];
        $c = Customer::create([
            'name' => $d['name'], 'phone' => $d['phone'] ?: null, 'email' => $d['email'] ?? null, 'document' => $d['document'] ?? null,
            'city' => $d['city'] ?? null, 'preferred_size' => $d['preferred_size'] ?? null, 'source' => $d['source'] ?? 'tienda',
            'stage' => 'prospecto', 'branch_id' => $this->user->branch_id, 'is_active' => true,
        ]);
        $c->tags()->sync(collect($d['tags'] ?? [])->map(fn ($t) => Tag::named($t)->id));
        $this->state = [];

        $links = [['Ver ficha', route('admin.clientes.show', $c)]];
        if ($c->phone) {
            $links[] = ['WhatsApp', $c->whatsappLink('¡Hola '.$c->firstName().'! Te escribimos de RadaWes.')];
        }

        return $this->reply("✅ Registré a **{$c->name}**.".($c->email ? ' Con su Gmail puede activar su cuenta en la página con «¿Olvidaste tu contraseña?».' : ''),
            links: $links, meta: ['action' => 'nuevo_cliente', 'customer_id' => $c->id]);
    }

    // =================================================================== ficha

    private function fillFicha(Parser $p, ?string $ask): void
    {
        $this->resolveCustomer($p, $ask);
    }

    private function nextFicha(?Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        if (empty($d['customer_id'])) {
            if (! empty($d['new_phone']) && empty($d['customer_options'])) {
                $this->state = [];

                return $this->reply("No hay ningún cliente con el celular **{$d['new_phone']}**. Para registrarlo di: «nuevo cliente *nombre* celular {$d['new_phone']}».");
            }

            return $this->askCustomer($prefix);
        }
        $c = Customer::with(['tags', 'sales' => fn ($q) => $q->where('status', 'pagada')->latest('sold_at'), 'sales.items.variant.product', 'interactions.user', 'tasks' => fn ($q) => $q->whereNull('done_at')])->findOrFail($d['customer_id']);
        $this->state = [];

        $lines = [
            "**{$c->name}** · {$c->stageLabel()}".($c->tags->isNotEmpty() ? ' · '.$c->tags->pluck('name')->implode(', ') : ''),
            '📱 '.($c->phone ?? '—').($c->preferred_size ? " · talla {$c->preferred_size}" : '').($c->city ? " · {$c->city}" : ''),
            '🧾 '.$c->sales->count().' compra(s) por '.money($c->sales->sum('total')).($c->sales->first() ? ' · última '.$c->sales->first()->sold_at->diffForHumans() : ''),
        ];
        if ($c->tasks->isNotEmpty()) {
            $lines[] = '📅 Pendiente: '.$c->tasks->map(fn ($t) => $t->title.' ('.$t->due_at->isoFormat('ddd D MMM').')')->implode('; ');
        }
        $rows = $c->interactions->take(3)->map(fn ($i) => [$i->created_at->isoFormat('D MMM'), $i->typeLabel().': '.Str::limit($i->body, 80)])
            ->merge($c->sales->take(3)->map(fn ($s) => [$s->sold_at->isoFormat('D MMM'), '🧾 '.$s->items->map(fn ($it) => $it->quantity.'× '.$it->variant->product->name.' '.$it->variant->label())->implode(', ')]))
            ->sortByDesc(0)->values()->all();
        $links = [['Ver ficha completa', route('admin.clientes.show', $c)]];
        if ($c->phone) {
            $links[] = ['WhatsApp', $c->whatsappLink('¡Hola '.$c->firstName().'!')];
        }

        return $this->reply(implode("\n", $lines), table: $rows ? [['Fecha', 'Último movimiento'], $rows] : null, links: $links, meta: ['action' => 'ficha', 'customer_id' => $c->id]);
    }

    // =================================================================== notas

    private function fillNota(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        if ($ask === 'body') {
            $d['body'] = Str::ucfirst(trim($p->t, ' .'));

            return;
        }
        $this->resolveCustomer($p, $ask);
        $d['type'] ??= $p->interactionType();
        if (! isset($d['body'])) {
            $body = str_contains($p->t, ':') ? trim(Str::after($p->t, ':')) : null;
            if (! $body && ! empty($d['customer_id']) && $ask !== 'customer') {
                $names = collect(preg_split('/[^a-z]+/', Parser::fold(Customer::find($d['customer_id'])->name)))->filter(fn ($w) => mb_strlen($w) >= 3)->map(fn ($w) => preg_quote($w, '/'));
                $body = $p->after('/\b(?:'.$names->implode('|').')\b(?:\s+(?:'.$names->implode('|').')\b)*(?:\s+(?:que|y))?/');
            }
            if ($body && mb_strlen($body) >= 3) {
                $d['body'] = Str::ucfirst(trim($body, ' .'));
            }
        }
    }

    private function nextNota(?Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        if (empty($d['customer_id'])) {
            return $this->askCustomer($prefix, '¿Para qué cliente es la nota? (nombre o celular)');
        }
        $c = Customer::find($d['customer_id']);
        if (empty($d['body'])) {
            return $this->ask('body', $prefix."¿Qué anoto en la ficha de **{$c->name}**?");
        }

        return $this->confirmCard($prefix.'Voy a anotar en el historial:', [
            ['Cliente', $this->customerLabel($c)],
            ['Tipo', \App\Models\CustomerInteraction::TYPES[$d['type']] ?? 'Nota'],
            ['Nota', $d['body']],
        ]);
    }

    private function executeNota(): array
    {
        $d = $this->state['data'];
        $c = Customer::findOrFail($d['customer_id']);
        $c->interactions()->create(['user_id' => $this->user->id, 'type' => $d['type'], 'body' => $d['body']]);
        if ($c->stage === 'prospecto' && $d['type'] !== 'nota') {
            $c->update(['stage' => 'interesado']);
        }
        $this->state = [];

        return $this->reply("✅ Anotado en la ficha de **{$c->name}**.", links: [['Ver ficha', route('admin.clientes.show', $c)]], meta: ['action' => 'nota', 'customer_id' => $c->id]);
    }

    // =================================================================== seguimientos

    private function fillTarea(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        $when = $p->when();
        if ($when) {
            $d['due_at'] = $when['at']->toIso8601String();
        }
        if ($ask === 'title') {
            $d['title'] = Str::ucfirst(trim($p->t, ' .'));
        } elseif (! isset($d['title']) && $ask !== 'when') {
            $title = $this->taskTitle($p, $when['spans'] ?? []);
            if (mb_strlen($title) >= 4) {
                $d['title'] = $title;
            }
        }
        if ($ask !== 'when' && $ask !== 'title') {
            $this->resolveCustomer($p, $ask);
            if ($ask === 'customer' && $p->says('/\b(ninguno|sin cliente|nadie|no es de un cliente)\b/')) {
                $d['customer_none'] = true;
            }
        }
    }

    /** "Recuérdame llamar a Carlos el viernes a las 10" → "Llamar a Carlos" */
    private function taskTitle(Parser $p, array $spans): string
    {
        $a = $p->a;
        $cut = [];
        if (preg_match('/^\W*(?:por favor\s+)?(?:recuerdame|recordarme|recordar|agenda(?:r|me)?(?:\s+un seguimiento)?|programa(?:r)?|crea(?:r)? (?:un )?(?:recordatorio|seguimiento)|(?:un )?recordatorio|seguimiento|tengo que|hay que|anota que)\s*(?:que\s+|para\s+|de\s+)?/', $a, $m)) {
            $cut[] = [0, strlen($m[0])];
        }
        foreach ($spans as $s) {
            if (($pos = strpos($a, $s)) !== false) {
                $cut[] = [$pos, strlen($s)];
            }
        }
        usort($cut, fn ($x, $y) => $x[0] <=> $y[0]);
        $out = '';
        $at = 0;
        foreach ($cut as [$pos, $len]) {
            if ($pos > $at) {
                $out .= $p->slice($at, mb_strlen(substr($a, $at, $pos - $at)));
            }
            $at = max($at, $pos + $len);
        }
        $out .= $p->slice($at, mb_strlen(substr($a, $at)));

        return Str::ucfirst(trim(preg_replace('/\s+/', ' ', $out), ' ,.;:'));
    }

    private function nextTarea(?Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        if (! empty($d['customer_options']) && empty($d['customer_id'])) {
            return $this->askCustomer($prefix);
        }
        if (empty($d['title'])) {
            return $this->ask('title', $prefix.'¿Qué hay que hacer? (ej.: llamar a Carlos para ofrecerle el CH-210)');
        }
        if (empty($d['due_at'])) {
            return $this->ask('when', $prefix.'¿Para **cuándo**? (ej.: mañana a las 10, el viernes, en 3 días)');
        }
        $c = ! empty($d['customer_id']) ? Customer::find($d['customer_id']) : null;

        return $this->confirmCard($prefix.'Voy a agendar este seguimiento:', [
            ['Qué', $d['title']],
            ['Cuándo', Carbon::parse($d['due_at'])->isoFormat('dddd D [de] MMMM, HH:mm')],
            ['Cliente', $c ? $this->customerLabel($c) : '—'],
            ['Responsable', $this->user->name],
        ]);
    }

    private function executeTarea(): array
    {
        $d = $this->state['data'];
        $t = CustomerTask::create([
            'customer_id' => $d['customer_id'] ?? null, 'user_id' => $this->user->id, 'created_by' => $this->user->id,
            'branch_id' => $this->user->branch_id, 'title' => $d['title'], 'due_at' => Carbon::parse($d['due_at']),
        ]);
        $this->state = [];

        return $this->reply('✅ Agendado para el '.$t->due_at->isoFormat('dddd D [de] MMMM [a las] HH:mm').'. Te aparecerá en «CRM · Seguimientos».',
            links: [['Ver seguimientos', route('admin.crm')]], meta: ['action' => 'tarea', 'task_id' => $t->id]);
    }

    private function nextTareas(?Product $product, string $prefix): array
    {
        $p = new Parser($this->state['data']['text'] ?? '');
        $q = CustomerTask::with('customer')->pending()->visibleTo($this->user)->orderBy('due_at');
        [$label, $tasks] = match (true) {
            $p->says('/\bmanana\b/') => ['para mañana', $q->whereDate('due_at', now()->addDay())->get()],
            $p->says('/\bsemana\b/') => ['de esta semana', $q->where('due_at', '<=', now()->addDays(7)->endOfDay())->get()],
            default => ['para hoy (y atrasados)', $q->where('due_at', '<=', now()->endOfDay())->get()],
        };
        $this->state = [];
        if ($tasks->isEmpty()) {
            return $this->reply("No tienes seguimientos {$label}. 🎉", links: [['CRM · Seguimientos', route('admin.crm')]], meta: ['action' => 'tareas']);
        }
        $rows = $tasks->map(fn ($t) => [($t->isOverdue() ? '⚠️ ' : '').$t->due_at->isoFormat('ddd D MMM HH:mm'), $t->title, $t->customer ? $this->customerLabel($t->customer) : '—'])->all();

        return $this->reply("Tienes **{$tasks->count()}** seguimiento(s) {$label}:", table: [['Cuándo', 'Qué', 'Cliente'], $rows],
            links: [['CRM · Seguimientos', route('admin.crm')]], meta: ['action' => 'tareas']);
    }

    private function fillTareas(Parser $p, ?string $ask): void
    {
        $this->state['data']['text'] = $p->a;
    }

    // ------------------------------------------------------------------- cerrar seguimiento

    private function fillTareaHecha(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        if ($ask === 'task' && ! empty($d['task_options']) && preg_match('/^\D*(\d)\D*$/', $p->a, $m) && isset($d['task_options'][$m[1] - 1])) {
            $d['task_id'] = $d['task_options'][$m[1] - 1];

            return;
        }
        $this->resolveCustomer($p, $ask);
    }

    private function nextTareaHecha(?Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        if (empty($d['task_id'])) {
            $tasks = CustomerTask::with('customer')->pending()->visibleTo($this->user)
                ->when(! empty($d['customer_id']), fn ($q) => $q->where('customer_id', $d['customer_id']))
                ->orderBy('due_at')->limit(6)->get();
            if ($tasks->isEmpty()) {
                $this->state = [];

                return $this->reply('No encontré seguimientos pendientes'.(! empty($d['customer_id']) ? ' de ese cliente' : '').'.');
            }
            if ($tasks->count() > 1 && empty($d['customer_id'])) {
                $this->state['data']['task_options'] = $tasks->pluck('id')->all();

                return $this->ask('task', $prefix."¿Cuál terminaste?\n".$tasks->values()->map(fn ($t, $i) => ($i + 1).'. '.$t->title.' ('.$t->due_at->isoFormat('ddd D MMM').')')->implode("\n"));
            }
            $this->state['data']['task_id'] = $tasks->first()->id;
        }
        $t = CustomerTask::with('customer')->find($this->state['data']['task_id']);

        return $this->confirmCard($prefix.'Marco como hecho:', [['Seguimiento', $t->title], ['Cliente', $t->customer ? $this->customerLabel($t->customer) : '—'], ['Era para', $t->due_at->isoFormat('ddd D MMM HH:mm')]]);
    }

    private function executeTareaHecha(): array
    {
        $t = CustomerTask::with('customer')->findOrFail($this->state['data']['task_id']);
        $t->update(['done_at' => now()]);
        if ($t->customer) {
            $type = preg_match('/llam/i', $t->title) ? 'llamada' : (preg_match('/whatsapp|escrib|mensaje/i', $t->title) ? 'whatsapp' : 'nota');
            $t->customer->interactions()->create(['user_id' => $this->user->id, 'type' => $type, 'body' => '✔ '.$t->title]);
        }
        $this->state = [];

        return $this->reply("✅ Listo, marqué «{$t->title}» como hecho.", meta: ['action' => 'tarea_hecha']);
    }

    // =================================================================== etapa, etiquetas y talla

    private function fillClienteCrm(Parser $p, ?string $ask): void
    {
        $d = &$this->state['data'];
        if ($ask !== 'change') {
            $this->resolveCustomer($p, $ask);
        }
        if ($stage = $p->stage()) {
            $d['stage'] = $stage;
        }
        if ($tags = array_values(array_filter($p->tagNames(), fn ($t) => ! in_array(Parser::fold($t), array_keys(Customer::STAGES), true)))) {
            $d['tags'] = $tags;
        }
        if ($size = $p->usualSize()) {
            $d['preferred_size'] = $size;
        }
    }

    private function nextClienteCrm(?Product $product, string $prefix): array
    {
        $d = $this->state['data'];
        if (empty($d['customer_id'])) {
            return $this->askCustomer($prefix);
        }
        $c = Customer::with('tags')->find($d['customer_id']);
        if (empty($d['stage']) && empty($d['tags']) && empty($d['preferred_size'])) {
            return $this->ask('change', $prefix."¿Qué cambio en **{$c->name}**? (ej.: «frecuente», «etiqueta VIP», «calza 41»)");
        }
        $rows = [['Cliente', $this->customerLabel($c)]];
        if (! empty($d['stage'])) {
            $rows[] = ['Etapa', $c->stageLabel().' → '.Customer::STAGES[$d['stage']][0]];
        }
        if (! empty($d['tags'])) {
            $rows[] = ['Etiquetas', '+ '.implode(', ', $d['tags'])];
        }
        if (! empty($d['preferred_size'])) {
            $rows[] = ['Talla habitual', ($c->preferred_size ?? '—').' → '.$d['preferred_size']];
        }

        return $this->confirmCard($prefix.'Voy a actualizar:', $rows);
    }

    private function executeClienteCrm(): array
    {
        $d = $this->state['data'];
        $c = Customer::findOrFail($d['customer_id']);
        $c->update(array_filter(['stage' => $d['stage'] ?? null, 'preferred_size' => $d['preferred_size'] ?? null]));
        if (! empty($d['tags'])) {
            $c->tags()->syncWithoutDetaching(collect($d['tags'])->map(fn ($t) => Tag::named($t)->id));
        }
        $this->state = [];

        return $this->reply("✅ Actualicé a **{$c->name}**.", links: [['Ver ficha', route('admin.clientes.show', $c)]], meta: ['action' => 'cliente_crm', 'customer_id' => $c->id]);
    }
}
