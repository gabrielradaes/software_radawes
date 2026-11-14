<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stock actual por variante y sucursal
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('min_quantity')->default(0);   // alerta de stock bajo
            $table->timestamps();
            $table->unique(['product_variant_id', 'branch_id']);
        });

        // Historial de movimientos (kardex): entradas, salidas, ajustes, ventas, traspasos
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['entrada', 'salida', 'ajuste', 'venta', 'traspaso']);
            $table->integer('quantity');            // positivo o negativo según el tipo
            $table->integer('balance_after');       // saldo resultante
            $table->string('reason', 255)->nullable();
            $table->nullableMorphs('reference');    // ej. Sale #12
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
    }
};
