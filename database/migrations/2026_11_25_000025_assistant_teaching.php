<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asistente que se entrena desde el panel (gratis, sin IA de pago):
 * - assistant_words: palabras propias del negocio ("preto" = color Negro, "Demócrata" = marca, "urbano" = clase, "depósito" = stock).
 * - assistant_examples: frases de ejemplo de cada acción ("cárgame esto al depósito" = ingresar calzado).
 *   Se guardan cuando alguien toca «¿Qué querías hacer?» o cuando el admin las enseña.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_words', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12);                 // color | marca | clase | palabra
            $table->string('phrase', 80);               // como lo dicen (sin tildes, minúsculas)
            $table->string('meaning', 120)->nullable(); // lo que significa (color, marca o texto que reemplaza)
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['kind', 'phrase']);
        });

        Schema::create('assistant_examples', function (Blueprint $table) {
            $table->id();
            $table->string('text', 300);
            $table->string('folded', 300);
            $table->string('intent', 30)->index();
            $table->string('source', 10)->default('admin'); // boton | admin
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('permissions')->updateOrInsert(['name' => 'assistant.teach'], ['label' => 'Enseñar al asistente (palabras y frases)', 'group' => 'admin', 'updated_at' => $now, 'created_at' => $now]);
        $pid = DB::table('permissions')->where('name', 'assistant.teach')->value('id');
        foreach (DB::table('roles')->whereIn('name', ['admin', 'supervisor'])->pluck('id') as $rid) {
            DB::table('permission_role')->updateOrInsert(['role_id' => $rid, 'permission_id' => $pid]);
        }

        // Lo que ya aprendimos de los chats reales
        foreach ([['color', 'preto', 'Negro'], ['color', 'tofi', 'Tofi'], ['color', 'marrom', 'Marrón'], ['palabra', 'deposito', 'stock']] as [$kind, $phrase, $meaning]) {
            DB::table('assistant_words')->updateOrInsert(['kind' => $kind, 'phrase' => $phrase], ['meaning' => $meaning, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_examples');
        Schema::dropIfExists('assistant_words');
        DB::table('permissions')->where('name', 'assistant.teach')->delete();
    }
};
