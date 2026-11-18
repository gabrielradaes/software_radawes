<?php

namespace Tests\Feature;

use App\Assistant\Dates;
use App\Assistant\Parser;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerTask;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\User;
use App\Services\VariantBuilder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** CRM (ventas, seguimientos, notas, etapas, etiquetas) desde el panel y desde el asistente. */
class CrmTest extends TestCase
{
    private function decir(User $user, string $text): array
    {
        return $this->actingAs($user)->post('/admin/asistente/mensaje', ['text' => $text], ['Accept' => 'application/json'])->assertOk()->json();
    }

    private function chelsea(int $stock = 5, string $branch = 'calacoto'): Product
    {
        $p = Product::create([
            'category_id' => Category::where('slug', 'botines')->value('id'), 'name' => 'Botín Chelsea', 'code' => 'CH-210',
            'slug' => 'botin-chelsea', 'base_price' => 450, 'is_active' => true, 'published_at' => now(),
        ]);
        $p->branches()->attach($this->branch($branch)->id, ['is_active' => true]);
        VariantBuilder::generate($p, [VariantBuilder::value('color', 'Negro')->id, VariantBuilder::value('color', 'Café')->id],
            [VariantBuilder::value('talla', '40')->id, VariantBuilder::value('talla', '41')->id]);
        Stock::where('branch_id', $this->branch($branch)->id)->update(['quantity' => $stock]);

        return $p->fresh();
    }

    private function stock(Product $p, string $talla, string $color, string $branch = 'calacoto'): int
    {
        $v = $p->variants()->with('attributeValues.attribute')->get()->first(fn ($v) => $v->valueOf('talla') === $talla && $v->valueOf('color') === $color);

        return (int) Stock::where('product_variant_id', $v->id)->where('branch_id', $this->branch($branch)->id)->value('quantity');
    }

    private function carlos(): Customer
    {
        return Customer::create(['name' => 'Carlos Mamani', 'phone' => '70011222', 'is_active' => true]);
    }

    // ------------------------------------------------------------------ panel

    public function test_venta_desde_el_panel_descuenta_stock_y_actualiza_el_cliente(): void
    {
        $p = $this->chelsea();
        $vend = $this->vendedor('calacoto');
        $variant = $p->variants()->first();

        $this->actingAs($vend)->get('/admin/ventas/nueva')->assertOk()->assertSee('CH-210');
        $this->actingAs($vend)->post('/admin/ventas', [
            'branch_id' => $this->branch('calacoto')->id, 'customer_phone' => '700 55 666', 'customer_name' => 'Rosa Quispe',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2, 'price' => 420]],
            'payment_method' => 'qr', 'channel' => 'tienda', 'discount' => 40,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $sale = Sale::firstOrFail();
        $this->assertSame('800.00', $sale->total);
        $this->assertSame(3, (int) Stock::where('product_variant_id', $variant->id)->where('branch_id', $this->branch('calacoto')->id)->value('quantity'));
        $rosa = Customer::where('phone', '70055666')->firstOrFail();
        $this->assertSame('cliente', $rosa->stage);
        $this->actingAs($vend)->get('/admin/ventas')->assertOk()->assertSee($sale->code);
        $this->actingAs($vend)->get("/admin/clientes/{$rosa->id}")->assertOk()->assertSee('Compra '.$sale->code);

        // Vender más de lo que hay → error claro
        $this->actingAs($vend)->post('/admin/ventas', [
            'branch_id' => $this->branch('calacoto')->id, 'items' => [['variant_id' => $variant->id, 'quantity' => 9]],
            'payment_method' => 'efectivo', 'channel' => 'tienda',
        ])->assertSessionHasErrors('items');

        // Otra tienda: prohibido para el vendedor; anular solo supervisor/admin y devuelve stock
        $this->actingAs($vend)->post('/admin/ventas', ['branch_id' => $this->branch('el-alto')->id, 'items' => [['variant_id' => $variant->id, 'quantity' => 1]], 'payment_method' => 'efectivo', 'channel' => 'tienda'])->assertForbidden();
        $this->actingAs($vend)->post("/admin/ventas/{$sale->id}/anular")->assertForbidden();
        $this->actingAs($this->supervisor('calacoto'))->post("/admin/ventas/{$sale->id}/anular", ['reason' => 'Cambio de talla'])->assertRedirect();
        $this->assertSame('anulada', $sale->fresh()->status);
        $this->assertSame(5, (int) Stock::where('product_variant_id', $variant->id)->where('branch_id', $this->branch('calacoto')->id)->value('quantity'));
        $this->actingAs($this->supervisor('el-alto'))->get("/admin/ventas/{$sale->id}")->assertForbidden();
    }

    public function test_crm_seguimientos_notas_etapas_y_etiquetas_en_el_panel(): void
    {
        $c = $this->carlos();
        $vend = $this->vendedor('calacoto');

        $this->actingAs($vend)->get('/admin/crm')->assertOk();
        $this->actingAs($vend)->post('/admin/crm/seguimientos', ['customer_id' => $c->id, 'title' => 'Avisar talla 41', 'due_at' => now()->addDay()->format('Y-m-d H:i')])->assertRedirect();
        $task = CustomerTask::firstOrFail();
        $this->actingAs($vend)->get('/admin/crm')->assertSee('Avisar talla 41');
        $this->actingAs($vend)->patch("/admin/crm/seguimientos/{$task->id}")->assertRedirect();
        $this->assertNotNull($task->fresh()->done_at);
        $this->actingAs($this->vendedor('el-alto'))->patch("/admin/crm/seguimientos/{$task->id}")->assertForbidden();

        $this->actingAs($vend)->post("/admin/clientes/{$c->id}/historial", ['type' => 'llamada', 'body' => 'Quiere mocasín café'])->assertRedirect();
        $this->assertSame('interesado', $c->fresh()->stage);
        $this->actingAs($vend)->patch("/admin/clientes/{$c->id}/crm", ['stage' => 'frecuente', 'preferred_size' => '41', 'source' => 'tiktok', 'tags' => 'vip, Mayorista'])->assertRedirect();
        $c->refresh();
        $this->assertSame(['frecuente', '41', 'tiktok'], [$c->stage, $c->preferred_size, $c->source]);
        $this->assertEqualsCanonicalizing(['VIP', 'Mayorista'], $c->tags->pluck('name')->all());

        $this->actingAs($vend)->get("/admin/clientes/{$c->id}")->assertOk()->assertSee('Quiere mocasín café')->assertSee('VIP');
        $this->actingAs($vend)->get('/admin/clientes?etapa=frecuente')->assertSee('Carlos Mamani');
        $this->actingAs($vend)->get('/admin/clientes?etapa=perdido')->assertDontSee('Carlos Mamani');
    }

    // ------------------------------------------------------------------ asistente

    public function test_entiende_celulares_correos_y_fechas(): void
    {
        $p = new Parser('nuevo cliente Carlos Mamani celular 700 11 222, carlos punto mamani arroba gmail punto com, de El Alto, calza 41, nos conoció por tiktok');
        $this->assertSame('nuevo_cliente', $p->intent());
        $this->assertSame('Carlos Mamani', $p->personName());
        $this->assertSame('70011222', $p->phone());
        $this->assertSame('carlos.mamani@gmail.com', $p->email());
        $this->assertSame('El Alto', $p->city());
        $this->assertSame('41', $p->usualSize());
        $this->assertSame('tiktok', $p->source());

        $martes = Carbon::parse('2026-11-17 11:00');
        $this->assertSame('2026-11-20 10:00', Dates::parse('el viernes a las 10', $martes)['at']->format('Y-m-d H:i'));
        $this->assertSame('2026-11-18 15:00', Dates::parse(Parser::fold('mañana a las 3 de la tarde'), $martes)['at']->format('Y-m-d H:i'));
        $this->assertSame('2026-11-20 10:00', Dates::parse('en 3 dias', $martes)['at']->format('Y-m-d H:i'));

        $this->assertSame('tarea', (new Parser('recuérdame llamar a Carlos el viernes'))->intent());
        $this->assertSame('tareas', (new Parser('¿qué seguimientos tengo hoy?'))->intent());
        $this->assertSame('nota', (new Parser('nota para Carlos: quiere talla 41'))->intent());
        $this->assertSame('reporte', (new Parser('¿cuánto vendimos hoy?'))->intent());
        $this->assertSame('precio', (new Parser('cambia el precio del CH-210 a 480'))->intent());
        $this->assertSame('traspaso', (new Parser('traspasa 2 pares del CH-210 de Calacoto a El Alto'))->intent());
        $this->assertSame('nuevo_usuario', (new Parser('nuevo vendedor Juan Pérez'))->intent());
    }

    public function test_registrar_cliente_ver_ficha_nota_y_etapa_por_el_asistente(): void
    {
        $vend = $this->vendedor('calacoto');
        $r = $this->decir($vend, 'nuevo cliente Carlos Mamani celular 70011222, carlos.mamani@gmail.com, calza 41');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->decir($vend, 'sí');
        $c = Customer::where('phone', '70011222')->firstOrFail();
        $this->assertSame(['Carlos Mamani', 'carlos.mamani@gmail.com', '41'], [$c->name, $c->email, $c->preferred_size]);

        // Duplicado
        $this->assertStringContainsString('ya está registrado', $this->decir($vend, 'nuevo cliente Carlitos celular 70011222')['text']);

        // Ficha por celular o nombre
        $this->assertStringContainsString('Carlos Mamani', $this->decir($vend, '¿quién es 70011222?')['text']);
        $this->assertStringContainsString('Carlos Mamani', $this->decir($vend, 'ficha de Carlos')['text']);

        // Nota
        $r = $this->decir($vend, 'nota para Carlos Mamani: quiere el mocasín café talla 41');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->decir($vend, 'sí');
        $this->assertSame('Quiere el mocasín café talla 41', $c->interactions()->first()->body);

        // Etapa y etiqueta
        $r = $this->decir($vend, 'marca a Carlos como frecuente y etiquétalo VIP');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->decir($vend, 'sí');
        $c->refresh();
        $this->assertSame('frecuente', $c->stage);
        $this->assertSame(['VIP'], $c->tags->pluck('name')->all());
    }

    public function test_seguimientos_por_el_asistente(): void
    {
        $c = $this->carlos();
        $vend = $this->vendedor('calacoto');

        $r = $this->decir($vend, 'recuérdame llamar a Carlos el viernes a las 10');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->decir($vend, 'sí');
        $t = CustomerTask::firstOrFail();
        $this->assertSame('Llamar a Carlos', $t->title);
        $this->assertSame($c->id, $t->customer_id);
        $this->assertSame(5, $t->due_at->dayOfWeek);
        $this->assertSame('10:00', $t->due_at->format('H:i'));

        // Con un resumen pendiente, otra orden no se toma como corrección
        $this->decir($vend, 'recuérdame escribirle a Carlos el lunes');
        $r = $this->decir($vend, '¿cuánto vendimos hoy?');
        $this->assertStringContainsString('quedó sin guardar', $r['text']);
        $this->assertSame(1, CustomerTask::count());

        // Sin fecha → pregunta
        $this->assertStringContainsString('cuándo', $this->decir($vend, 'recuérdame enviar fotos del CH-210 a Carlos')['text']);
        $this->decir($vend, 'mañana en la mañana');
        $this->decir($vend, 'sí');
        $this->assertSame(2, CustomerTask::count());

        $t->update(['due_at' => now()->subHour()]);
        $r = $this->decir($vend, '¿qué seguimientos tengo hoy?');
        $this->assertStringContainsString('Llamar a Carlos', json_encode($r['table'], JSON_UNESCAPED_UNICODE));

        $r = $this->decir($vend, 'ya llamé a Carlos');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->decir($vend, 'sí');
        $this->assertSame(1, CustomerTask::whereNotNull('done_at')->count());
        $this->assertSame('llamada', $c->interactions()->first()->type);

        // Otro vendedor no ve mis seguimientos
        $this->assertStringContainsString('No tienes', $this->decir($this->vendedor('el-alto'), 'mis seguimientos')['text']);
    }

    public function test_venta_con_cliente_reporte_y_cliente_nuevo_por_el_asistente(): void
    {
        $p = $this->chelsea();
        $c = $this->carlos();
        $vend = $this->vendedor('calacoto');

        $r = $this->decir($vend, 'vendí 1 CH-210 talla 41 café a Carlos, pagó con QR');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->assertStringContainsString('Carlos Mamani', json_encode($r['table'], JSON_UNESCAPED_UNICODE));
        $this->decir($vend, 'sí');
        $sale = Sale::firstOrFail();
        $this->assertSame([$c->id, 'qr', '450.00'], [$sale->customer_id, $sale->payment_method, $sale->total]);
        $this->assertSame(4, $this->stock($p, '41', 'Café'));
        $this->assertSame('cliente', $c->fresh()->stage);

        // Cliente nuevo por celular
        $this->assertStringContainsString('Cómo se llama', $this->decir($vend, 'vendí 2 CH-210 talla 40 negro al 76543210')['text']);
        $r = $this->decir($vend, 'Ana Flores');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->decir($vend, 'sí');
        $this->assertSame('Ana Flores', Customer::where('phone', '76543210')->value('name'));
        $this->assertSame(3, $this->stock($p, '40', 'Negro'));

        // Reporte (el vendedor tiene sales.view)
        $r = $this->decir($vend, '¿cuánto vendimos hoy?');
        $this->assertStringContainsString('1.350,00', $r['text']);
        $this->assertSame('Botín Chelsea', $r['table'][1][0][0]);
    }

    public function test_precio_ocultar_traspaso_clase_y_usuario_por_el_asistente(): void
    {
        $p = $this->chelsea();
        $admin = $this->admin();

        $r = $this->decir($admin, 'cambia el precio del CH-210 a 480');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->decir($admin, 'sí');
        $this->assertSame('480.00', $p->fresh()->base_price);

        $this->decir($admin, 'oculta el CH-210');
        $this->decir($admin, 'sí');
        $this->assertFalse($p->fresh()->is_active);
        $this->decir($admin, 'activa el CH-210');
        $this->decir($admin, 'sí');
        $this->assertTrue($p->fresh()->is_active);

        $r = $this->decir($admin, 'traspasa 2 pares del CH-210 talla 40 negro de Calacoto a El Alto');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->decir($admin, 'sí');
        $this->assertSame(3, $this->stock($p, '40', 'Negro'));
        // En 2 pasos: en camino hasta que El Alto confirma
        $this->assertSame(0, $this->stock($p, '40', 'Negro', 'el-alto'));
        $tr = \App\Models\Transfer::firstOrFail();
        $this->assertSame('enviado', $tr->status);
        $this->actingAs($this->vendedor('el-alto'))->post("/admin/traspasos/{$tr->id}/recibir")->assertRedirect();
        $this->assertSame(2, $this->stock($p, '40', 'Negro', 'el-alto'));

        $this->decir($admin, 'nueva clase Pantuflas');
        $this->decir($admin, 'sí');
        $this->assertTrue(Category::where('name', 'Pantuflas')->where('parent_id', Category::where('slug', 'calzado')->value('id'))->exists());

        $r = $this->decir($admin, 'nuevo vendedor Juan Pérez juan.perez@gmail.com en El Alto');
        $this->assertTrue($r['confirm'], $r['text']);
        $this->decir($admin, 'sí');
        $u = User::where('email', 'juan.perez@gmail.com')->firstOrFail();
        $this->assertSame(['Juan Pérez', 'empleado', 'el-alto'], [$u->name, $u->role->name, $u->branch->slug]);

        // Gmail obligatorio
        $this->assertStringContainsString('no es Gmail', $this->decir($admin, 'nuevo supervisor Luis Rojas luis@hotmail.com en Calacoto')['text']);
        $this->decir($admin, 'cancelar');

        // Un vendedor no puede nada de esto
        $vend = $this->vendedor('calacoto');
        foreach (['cambia el precio del CH-210 a 500', 'oculta el CH-210', 'nueva clase Ojotas', 'nuevo vendedor Pepe Paz pepe@gmail.com en Calacoto', 'traspasa 1 par del CH-210 talla 40 negro de Calacoto a El Alto'] as $orden) {
            $this->assertStringContainsString('no permite', $this->decir($vend, $orden)['text'], $orden);
        }
        $this->assertSame('480.00', $p->fresh()->base_price);
    }
}
