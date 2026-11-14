<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        // La sucursal original (slug la-paz) pasa a ser "Comercio"
        Branch::where('slug', 'la-paz')->update(['slug' => 'comercio']);

        $stores = [
            [
                'slug' => 'comercio',
                'name' => 'RyE Made in Brazil · Comercio',
                'brand_name' => 'RyE',
                'brand_tagline' => 'Made in Brazil',
                'brand_small' => 'RadaWes',
                'tagline' => 'Calzado de cuero legítimo hecho en Brasil',
                'description' => 'Zapatos de traje, mocasines, botines, montañeras y zapatenis. Estilo, comodidad y calidad a tu alcance, en pleno centro de La Paz.',
                'city' => 'La Paz',
                'address' => 'Calle Comercio Nº 920, entre Genaro Sanjinez y Yanacocha',
                'phone' => '77718360',
                'whatsapp' => '59177718360',
                'opening_hours' => 'Lunes a sábado 9:00 – 19:30',
                'latitude' => -16.4955, 'longitude' => -68.1336,
                'logo_path' => 'images/brands/rye.png',
                'color_primary' => '#0f5132',   // verde Brasil
                'color_accent' => '#f2c94c',    // amarillo
                'color_surface' => '#f6f9f4',
                'is_main' => true,
                'links' => [
                    ['facebook', 'RadaWes', 'https://www.facebook.com/profile.php?id=61594001403717'],
                    ['tiktok', '@radawes', 'https://www.tiktok.com/@radawes'],
                    ['tiktok', '@radawes_dobrazil', 'https://www.tiktok.com/@radawes_dobrazil'],
                ],
            ],
            [
                'slug' => 'calacoto',
                'name' => 'RadaWes · Calacoto',
                'brand_name' => 'RadaWes',
                'brand_tagline' => 'Shoes Made in Brasil',
                'brand_small' => null,
                'tagline' => 'Calzado elegante para hombre hecho en Brasil',
                'description' => 'Nuestra tienda en la zona Sur. Zapatos de cuero brasileño con atención personalizada.',
                'city' => 'La Paz',
                'address' => 'Calacoto, zona Sur (dirección por confirmar)',
                'phone' => '77718360',
                'whatsapp' => '59177718360',
                'opening_hours' => 'Lunes a sábado 10:00 – 20:00',
                'latitude' => -16.5404, 'longitude' => -68.0835,
                'logo_path' => 'images/brands/radawes.png',
                'color_primary' => '#6b3e1e',   // marrón
                'color_accent' => '#b3121b',    // rojo
                'color_surface' => '#ffffff',   // blanco
                'is_main' => false,
                'links' => [
                    ['tiktok', '@radawes', 'https://www.tiktok.com/@radawes'],
                ],
            ],
            [
                'slug' => 'el-alto',
                'name' => 'RyC · El Alto',
                'brand_name' => 'RyC',
                'brand_tagline' => 'Calzado y motos',
                'brand_small' => 'RadaWes',
                'tagline' => 'Calzado de cuero y motos para la ciudad',
                'description' => 'Calzado brasileño, motos y accesorios de distintas procedencias. Consulta financiamiento.',
                'city' => 'El Alto',
                'address' => 'El Alto (dirección por confirmar)',
                'phone' => '77718360',
                'whatsapp' => '59177718360',
                'opening_hours' => 'Lunes a sábado 9:00 – 19:00',
                'latitude' => -16.5047, 'longitude' => -68.1633,
                'logo_path' => null,
                'color_primary' => '#6b3e1e',
                'color_accent' => '#b3121b',
                'color_surface' => '#ffffff',
                'is_main' => false,
                'links' => [],
            ],
        ];

        foreach ($stores as $s) {
            $links = $s['links'];
            unset($s['links']);
            $branch = Branch::updateOrCreate(['slug' => $s['slug']], [...$s, 'country' => 'Bolivia', 'is_active' => true]);
            $branch->socialLinks()->delete();
            foreach ($links as $i => [$platform, $handle, $url]) {
                $branch->socialLinks()->create(compact('platform', 'handle', 'url') + ['sort_order' => $i]);
            }
        }

        // Dominio de cada tienda: en producción subdominios de STORE_BASE_DOMAIN; en local *.localhost
        $base = env('STORE_BASE_DOMAIN');
        $domains = $base
            ? ['comercio' => "comercio.$base", 'calacoto' => "calacoto.$base", 'el-alto' => "elalto.$base"]
            : ['comercio' => 'rye.localhost', 'calacoto' => 'radawes.localhost', 'el-alto' => 'ryc.localhost'];
        foreach ($domains as $slug => $domain) {
            Branch::where('slug', $slug)->update(['domain' => $domain]);
        }

        // Usuarios de prueba (contraseña "password"): NUNCA en producción
        if (app()->environment('production')) {
            return;
        }
        $byslug = Branch::pluck('id', 'slug');
        $users = [
            ['Administrador RyE', 'admin@rye.test', Role::ADMIN, null],
            ['Supervisor Comercio', 'supervisor@rye.test', Role::SUPERVISOR, 'comercio'],
            ['Vendedor Comercio', 'vendedor@rye.test', Role::EMPLEADO, 'comercio'],
            ['Supervisor Calacoto', 'supervisor.calacoto@rye.test', Role::SUPERVISOR, 'calacoto'],
            ['Vendedor Calacoto', 'vendedor.calacoto@rye.test', Role::EMPLEADO, 'calacoto'],
            ['Supervisor El Alto', 'supervisor.elalto@rye.test', Role::SUPERVISOR, 'el-alto'],
            ['Vendedor El Alto', 'vendedor.elalto@rye.test', Role::EMPLEADO, 'el-alto'],
        ];
        foreach ($users as [$name, $email, $role, $slug]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'role_id' => Role::where('name', $role)->value('id'),
                'branch_id' => $slug ? $byslug[$slug] : null,
                'password' => 'password',
                'is_active' => true,
            ]);
        }
    }
}
