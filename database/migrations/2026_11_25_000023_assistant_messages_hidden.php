<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Nueva conversación" ya no borra los mensajes del asistente: solo los oculta de la pantalla de quien los escribió.
 * Así el administrador puede revisar qué se le pidió y qué respondió (y mejorar lo que no entiende).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('meta');
            $table->index(['user_id', 'hidden_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'hidden_at', 'id']);
            $table->dropColumn('hidden_at');
        });
    }
};
