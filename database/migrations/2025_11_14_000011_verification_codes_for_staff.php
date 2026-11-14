<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Los códigos ahora sirven para clientes (customer_id) o para el equipo del panel (user_id)
        Schema::table('verification_codes', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->change();
            $table->foreignId('user_id')->nullable()->after('customer_id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'purpose', 'used_at']);
        });

        // "Empleado" se muestra como "Vendedor" (el nombre interno del rol no cambia)
        DB::table('roles')->where('name', 'empleado')->update([
            'label' => 'Vendedor',
            'description' => 'Vendedor de tienda: consulta stock, registra clientes y ventas de su sucursal.',
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'empleado')->update(['label' => 'Empleado']);

        Schema::table('verification_codes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'purpose', 'used_at']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
