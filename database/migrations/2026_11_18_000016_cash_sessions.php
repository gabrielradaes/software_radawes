<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Caja diaria por tienda: apertura, ventas del turno por forma de pago, cierre y arqueo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->dateTime('closed_at')->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->json('totals')->nullable();          // por forma de pago al cerrar
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'closed_at']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $t) => $t->dropConstrainedForeignId('cash_session_id'));
        Schema::dropIfExists('cash_sessions');
    }
};
