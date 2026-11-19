<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - branches.web_stock_buffer: pares que la tienda se guarda y NO ofrece en la página (ej. 1 = si queda 1, la web dice "agotado")
 * - reservations.source: "tienda" o "web" (el cliente separó el par desde la página)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $t) {
            $t->unsignedSmallInteger('web_stock_buffer')->default(0)->after('opening_hours');
        });
        Schema::table('reservations', function (Blueprint $t) {
            $t->string('source', 10)->default('tienda')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $t) => $t->dropColumn('source'));
        Schema::table('branches', fn (Blueprint $t) => $t->dropColumn('web_stock_buffer'));
    }
};
