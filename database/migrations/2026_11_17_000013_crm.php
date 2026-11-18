<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM de clientes:
 * - customers: etapa (embudo), cómo nos conoció, talla habitual y tienda de referencia
 * - tags + customer_tag: etiquetas (VIP, mayorista…) normalizadas
 * - customer_interactions: historial (notas, llamadas, WhatsApp, visitas)
 * - customer_tasks: seguimientos con fecha (recordatorios para el equipo)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('stage', 20)->default('prospecto')->after('notes');      // prospecto, interesado, cliente, frecuente, perdido
            $table->string('source', 20)->nullable()->after('stage');              // tienda, whatsapp, facebook, tiktok, web, referido
            $table->string('preferred_size', 10)->nullable()->after('source');     // talla habitual
            $table->foreignId('branch_id')->nullable()->after('preferred_size')->constrained()->nullOnDelete();
            $table->index('stage');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('color', 20)->default('slate');
            $table->timestamps();
        });

        Schema::create('customer_tag', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_id', 'tag_id']);
        });

        Schema::create('customer_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('nota');   // nota, llamada, whatsapp, visita
            $table->text('body');
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('customer_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();          // responsable
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->dateTime('due_at');
            $table->dateTime('done_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'done_at', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tasks');
        Schema::dropIfExists('customer_interactions');
        Schema::dropIfExists('customer_tag');
        Schema::dropIfExists('tags');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropIndex(['stage']);
            $table->dropColumn(['stage', 'source', 'preferred_size']);
        });
    }
};
