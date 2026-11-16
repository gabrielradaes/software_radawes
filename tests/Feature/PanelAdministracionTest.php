<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\Palettes;
use Tests\TestCase;

/** Panel: usuarios por rol, clientes, sucursales, colores, recuperar contraseña y despliegue. */
class PanelAdministracionTest extends TestCase
{
    public function test_crear_supervisor_vendedor_y_otro_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/usuarios/create')->assertOk();
        $roles = Role::pluck('id', 'name');
        $base = ['password' => 'clave12345', 'password_confirmation' => 'clave12345', 'is_active' => 1];

        $this->actingAs($admin)->post('/admin/usuarios', $base + ['name' => 'Daniela', 'email' => 'Daniela.Sup@Gmail.com', 'role_id' => $roles['supervisor'], 'branch_id' => $this->branch()->id])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/usuarios');
        $this->actingAs($admin)->post('/admin/usuarios', $base + ['name' => 'Vendedor', 'email' => 'vende.uno@gmail.com', 'role_id' => $roles['empleado'], 'branch_id' => $this->branch()->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/usuarios', $base + ['name' => 'Socio', 'email' => 'socio.admin@gmail.com', 'role_id' => $roles['admin']])
            ->assertSessionHasNoErrors();

        $this->assertSame('daniela.sup@gmail.com', User::where('name', 'Daniela')->value('email'));
        $this->actingAs($admin)->get('/admin/usuarios')->assertSee('Vendedor')->assertSee('Socio');

        // Errores claros y en español
        $this->actingAs($admin)->post('/admin/usuarios', $base + ['name' => 'X', 'email' => 'daniela.sup@gmail.com', 'role_id' => $roles['supervisor'], 'branch_id' => $this->branch()->id])
            ->assertSessionHasErrors(['email' => 'Ya hay un usuario del panel con ese Gmail. Búscalo en la lista de Usuarios (quizás ya se creó).']);
        $this->actingAs($admin)->post('/admin/usuarios', $base + ['name' => 'X', 'email' => 'x@hotmail.com', 'role_id' => $roles['empleado'], 'branch_id' => $this->branch()->id])
            ->assertSessionHasErrors('email');
        $this->actingAs($admin)->post('/admin/usuarios', $base + ['name' => 'X', 'email' => 'sin.tienda@gmail.com', 'role_id' => $roles['empleado']])
            ->assertSessionHasErrors('branch_id');

        // El nuevo supervisor puede entrar al panel con su contraseña
        $this->post('/admin/login', ['email' => 'daniela.sup@gmail.com', 'password' => 'clave12345'])->assertRedirect('/admin');
    }

    public function test_editar_y_eliminar_usuario(): void
    {
        $admin = $this->admin();
        $vend = $this->vendedor();
        $this->actingAs($admin)->put("/admin/usuarios/{$vend->id}", [
            'name' => 'Vendedor Renombrado', 'email' => $vend->email, 'role_id' => $vend->role_id, 'branch_id' => $this->branch('el-alto')->id, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame('el-alto', $vend->fresh()->branch->slug);

        $this->actingAs($admin)->delete("/admin/usuarios/{$vend->id}")->assertRedirect();
        $this->assertNull(User::find($vend->id));
        $this->actingAs($admin)->delete("/admin/usuarios/{$admin->id}")->assertSessionHasErrors('user');
    }

    public function test_recuperar_contrasena_del_panel_con_codigo(): void
    {
        $sup = $this->supervisor();
        $this->get('/admin/login')->assertOk()->assertSee('¿Olvidaste tu contraseña?');
        $this->post('/admin/recuperar', ['email' => strtoupper($sup->email)])->assertRedirect('/admin/recuperar/codigo');
        $code = $this->ultimoCodigo($sup->email);

        $this->post('/admin/recuperar/codigo', ['code' => '000000', 'password' => 'NuevaClave99', 'password_confirmation' => 'NuevaClave99'])->assertSessionHasErrors('code');
        $this->post('/admin/recuperar/codigo', ['code' => $code, 'password' => 'NuevaClave99', 'password_confirmation' => 'NuevaClave99'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($sup->fresh());

        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => $sup->email, 'password' => 'NuevaClave99'])->assertRedirect('/admin');
    }

    public function test_registrar_cliente_en_tienda(): void
    {
        $vend = $this->vendedor();
        $this->actingAs($vend)->get('/admin/clientes/nuevo')->assertOk();
        $this->actingAs($vend)->post('/admin/clientes', ['name' => 'Carlos Mamani', 'phone' => '700 11 222', 'email' => 'carlos.m@gmail.com', 'city' => 'La Paz', 'document' => '1234567'])
            ->assertRedirect();
        $c = Customer::where('phone', '70011222')->firstOrFail();
        $this->assertNull($c->password);

        $this->actingAs($vend)->post('/admin/clientes', ['name' => 'Repetido', 'phone' => '70011222'])->assertRedirect("/admin/clientes/{$c->id}");
        $this->actingAs($vend)->post('/admin/clientes', ['name' => 'Mal correo', 'phone' => '70099999', 'email' => 'a@yahoo.com'])->assertSessionHasErrors('email');
        $this->actingAs($vend)->get("/admin/clientes/{$c->id}")->assertOk()->assertSee('Carlos Mamani');

        $admin = $this->admin();
        $this->actingAs($admin)->patch("/admin/clientes/{$c->id}", ['notes' => 'Talla 41', 'is_active' => 1])->assertRedirect();
        $this->assertSame('Talla 41', $c->fresh()->notes);
    }

    public function test_sucursales_y_colores(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/sucursales')->assertOk();
        $cal = $this->branch('calacoto');

        $this->actingAs($admin)->put("/admin/sucursales/{$cal->id}", [
            'name' => $cal->name, 'city' => 'La Paz', 'country' => 'Bolivia', 'whatsapp' => '+591 777 18360',
            'address' => 'Av. Ballivián 123', 'domain' => 'HTTPS://www.Calacoto.Ejemplo.com/', 'is_active' => 1,
            'links' => [['platform' => 'tiktok', 'handle' => '@radawes', 'url' => 'https://www.tiktok.com/@radawes']],
        ])->assertSessionHasNoErrors();
        $cal->refresh();
        $this->assertSame('59177718360', $cal->whatsapp);
        $this->assertSame('calacoto.ejemplo.com', $cal->domain);
        $this->assertSame('Av. Ballivián 123', $cal->address);

        $this->actingAs($admin)->post('/admin/sucursales', ['name' => 'RyE Sopocachi', 'city' => 'La Paz', 'country' => 'Bolivia', 'whatsapp' => '59170000000', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Branch::where('name', 'RyE Sopocachi')->exists());

        $this->actingAs($admin)->get("/admin/colores?tienda={$cal->id}")->assertOk();
        $this->actingAs($admin)->post('/admin/colores', ['branch_id' => $cal->id, 'paleta' => 12])->assertRedirect();
        $this->assertSame(12, Palettes::match(...array_values($cal->fresh()->only('color_primary', 'color_accent', 'color_surface'))));

        $sup = $this->supervisor('calacoto');
        $this->actingAs($sup)->post('/admin/colores', ['branch_id' => $this->branch('comercio')->id, 'paleta' => 3])->assertForbidden();
        $this->actingAs($sup)->get('/admin/sucursales')->assertForbidden();
    }

    public function test_puerta_de_despliegue_protegida(): void
    {
        config(['app.deploy_token' => str_repeat('k', 48)]);
        $this->post('/_deploy')->assertForbidden();
        $this->post('/_deploy', [], ['X-Deploy-Token' => 'mal'])->assertForbidden();
        $this->post('/_deploy/estado', [], ['X-Deploy-Token' => str_repeat('k', 48)])->assertOk()->assertJson(['ok' => true]);
        $this->post('/_deploy', [], ['X-Deploy-Token' => str_repeat('k', 48)])->assertOk()->assertJson(['ok' => true]);
    }
}
