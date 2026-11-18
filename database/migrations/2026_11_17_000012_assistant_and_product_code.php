<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - products.code: código del modelo (ej. CH-210), como lo dice el proveedor o la caja.
 * - assistant_messages: conversación con el asistente del panel (historial y registro de quién ordenó qué).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->unique()->after('name');
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12);              // user | assistant
            $table->text('text');
            $table->string('intent', 30)->nullable(); // consulta, nuevo_producto, entrada, salida, publicar…
            $table->json('meta')->nullable();         // acción ejecutada, ids creados, enlaces
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
