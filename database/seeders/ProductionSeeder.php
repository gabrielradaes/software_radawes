<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Base de datos inicial para el hosting: roles, las 3 tiendas, catálogo vacío con sus
 * clases y fichas técnicas, y UN administrador. Sin productos ni usuarios de prueba.
 *
 * Uso (lo ejecuta scripts/build-deploy.sh):
 *   APP_ENV=production STORE_BASE_DOMAIN=radawes.com ADMIN_EMAIL=… ADMIN_PASSWORD=… \
 *   php artisan migrate:fresh --seed --seeder=Database\\Seeders\\ProductionSeeder --force
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if (! $email || ! $password || strlen($password) < 12) {
            throw new RuntimeException('Define ADMIN_EMAIL y ADMIN_PASSWORD (mínimo 12 caracteres).');
        }

        $this->call([
            RoleSeeder::class,
            BranchSeeder::class,
            BaseCatalogSeeder::class,
            SpecSeeder::class,
            StoreCatalogSeeder::class,
        ]);

        User::updateOrCreate(['email' => $email], [
            'name' => 'Administrador',
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
            'branch_id' => null,
            'password' => $password,
            'is_active' => true,
        ]);
    }
}
