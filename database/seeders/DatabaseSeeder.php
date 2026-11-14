<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            BranchSeeder::class,
            CatalogSeeder::class,
            SpecSeeder::class,
            StoreCatalogSeeder::class,
            PostSeeder::class,
        ]);
    }
}
