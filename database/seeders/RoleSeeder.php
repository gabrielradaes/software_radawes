<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Catálogo y contenido
            ['name' => 'products.view',   'label' => 'Ver productos',           'group' => 'catalogo'],
            ['name' => 'products.manage', 'label' => 'Crear/editar productos',  'group' => 'catalogo'],
            ['name' => 'posts.manage',    'label' => 'Publicar en el feed',     'group' => 'contenido'],
            ['name' => 'comments.moderate', 'label' => 'Moderar comentarios',   'group' => 'contenido'],
            // Inventario
            ['name' => 'stock.view',      'label' => 'Ver stock',               'group' => 'inventario'],
            ['name' => 'stock.adjust',    'label' => 'Ajustar stock / entradas','group' => 'inventario'],
            ['name' => 'stock.transfer',  'label' => 'Traspasos entre sucursales', 'group' => 'inventario'],
            // Ventas
            ['name' => 'sales.create',    'label' => 'Registrar ventas',        'group' => 'ventas'],
            ['name' => 'sales.view',      'label' => 'Ver ventas',              'group' => 'ventas'],
            ['name' => 'sales.cancel',    'label' => 'Anular ventas',           'group' => 'ventas'],
            ['name' => 'leads.manage',    'label' => 'Gestionar leads WhatsApp','group' => 'ventas'],
            ['name' => 'customers.view',  'label' => 'Ver clientes y su actividad', 'group' => 'ventas'],
            ['name' => 'customers.manage','label' => 'Editar/desactivar clientes', 'group' => 'ventas'],
            // Administración
            ['name' => 'reports.view',    'label' => 'Ver reportes',            'group' => 'admin'],
            ['name' => 'users.manage',    'label' => 'Gestionar usuarios',      'group' => 'admin'],
            ['name' => 'branches.manage', 'label' => 'Gestionar sucursales',    'group' => 'admin'],
            ['name' => 'records.delete',  'label' => 'Eliminar registros (productos, publicaciones, usuarios…)', 'group' => 'admin'],
            ['name' => 'activity.view',   'label' => 'Ver el historial de actividad del equipo', 'group' => 'admin'],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(['name' => $p['name']], $p);
        }

        $roles = [
            Role::ADMIN => [
                'label' => 'Administrador',
                'description' => 'Control total: contenido, catálogo, stock, ventas, usuarios y sucursales.',
                'permissions' => Permission::pluck('name')->all(),
            ],
            Role::SUPERVISOR => [
                'label' => 'Supervisor',
                'description' => 'Encargado de sucursal: hace casi todo en su tienda (productos, stock, ventas, publicaciones) menos eliminar.',
                'permissions' => [
                    'products.view', 'products.manage', 'posts.manage', 'comments.moderate',
                    'stock.view', 'stock.adjust', 'stock.transfer',
                    'sales.create', 'sales.view', 'sales.cancel', 'leads.manage', 'customers.view', 'customers.manage', 'reports.view',
                    'activity.view',
                ],
            ],
            Role::EMPLEADO => [
                'label' => 'Vendedor',
                'description' => 'Vendedor de tienda: consulta stock, vende, atiende clientes y publica en la página de su tienda.',
                'permissions' => ['products.view', 'stock.view', 'sales.create', 'sales.view', 'leads.manage', 'customers.view', 'posts.manage'],
            ],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::updateOrCreate(['name' => $name], [
                'label' => $data['label'],
                'description' => $data['description'],
            ]);
            $role->permissions()->sync(Permission::whereIn('name', $data['permissions'])->pluck('id'));
        }
    }
}
