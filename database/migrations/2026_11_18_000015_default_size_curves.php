<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Curvas de tallas típicas para cargar compras rápido (se pueden editar en el panel). */
return new class extends Migration
{
    private const CURVES = [
        'Caballero 38-43 (normal)' => ['38' => 1, '39' => 2, '40' => 3, '41' => 3, '42' => 2, '43' => 1],
        'Caballero 39-44 (grande)' => ['39' => 1, '40' => 2, '41' => 3, '42' => 3, '43' => 2, '44' => 1],
        'Dama 34-39 (normal)' => ['34' => 1, '35' => 2, '36' => 3, '37' => 3, '38' => 2, '39' => 1],
        'Corrida 38-43 (1 de cada)' => ['38' => 1, '39' => 1, '40' => 1, '41' => 1, '42' => 1, '43' => 1],
    ];

    public function up(): void
    {
        foreach (self::CURVES as $name => $sizes) {
            if (DB::table('size_curves')->where('name', $name)->exists()) {
                continue;
            }
            $id = DB::table('size_curves')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($sizes as $size => $qty) {
                DB::table('size_curve_items')->insert(['size_curve_id' => $id, 'size' => $size, 'quantity' => $qty]);
            }
        }
    }

    public function down(): void
    {
        DB::table('size_curves')->whereIn('name', array_keys(self::CURVES))->delete();
    }
};
