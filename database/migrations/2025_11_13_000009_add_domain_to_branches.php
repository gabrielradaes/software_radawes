<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dominio propio de cada tienda: radawes.localhost (local) → radawes.com.bo (producción)
        Schema::table('branches', function (Blueprint $table) {
            $table->string('domain', 120)->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('branches', fn (Blueprint $t) => $t->dropColumn('domain'));
    }
};
