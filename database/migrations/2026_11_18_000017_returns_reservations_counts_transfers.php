<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - cash_movements: todo lo que entra/sale de la caja (ventas, adelantos, reembolsos, gastos)
 * - sale_returns: cambios de talla y devoluciones
 * - reservations: apartados con adelanto (el stock queda reservado: stocks.reserved)
 * - stock_counts: inventario físico (conteo y ajuste de diferencias)
 * - transfers: traspasos entre tiendas en 2 pasos (enviado → recibido)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);              // venta, anulacion, adelanto, reembolso, cobro, ingreso, egreso
            $table->string('payment_method', 20)->default('efectivo');
            $table->decimal('amount', 12, 2);        // + entra, − sale
            $table->nullableMorphs('reference');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        // Las ventas ya registradas en un turno pasan a ser movimientos de caja
        foreach (DB::table('sales')->whereNotNull('cash_session_id')->get() as $s) {
            DB::table('cash_movements')->insert([
                'cash_session_id' => $s->cash_session_id, 'user_id' => $s->user_id, 'type' => 'venta', 'payment_method' => $s->payment_method,
                'amount' => $s->total, 'reference_type' => 'App\\Models\\Sale', 'reference_id' => $s->id, 'note' => $s->code,
                'created_at' => $s->created_at, 'updated_at' => $s->updated_at,
            ]);
            if ($s->status === 'anulada') {
                DB::table('cash_movements')->insert([
                    'cash_session_id' => $s->cash_session_id, 'user_id' => $s->user_id, 'type' => 'anulacion', 'payment_method' => $s->payment_method,
                    'amount' => -$s->total, 'reference_type' => 'App\\Models\\Sale', 'reference_id' => $s->id, 'note' => $s->code,
                    'created_at' => $s->updated_at, 'updated_at' => $s->updated_at,
                ]);
            }
        }

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('type', 15);                       // cambio, devolucion
            $table->decimal('returned_value', 12, 2)->default(0);
            $table->decimal('given_value', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);    // + cobró al cliente, − le devolvió
            $table->string('payment_method', 20)->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('direction', 3);                   // in = vuelve a la tienda, out = se entrega
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedInteger('reserved')->default(0)->after('quantity');
        });
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();               // AP-CA-000001
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 15)->default('activo');    // activo, retirado, cancelado
            $table->decimal('total', 12, 2);
            $table->decimal('deposit', 12, 2)->default(0);
            $table->string('deposit_method', 20)->default('efectivo');
            $table->date('expires_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });
        Schema::create('reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 10)->default('parcial');   // parcial = solo lo contado; total = lo no contado queda en 0
            $table->string('status', 12)->default('abierto');   // abierto, cerrado, cancelado
            $table->string('notes', 255)->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('counted')->default(0);
            $table->integer('system_qty')->nullable();
            $table->integer('difference')->nullable();
            $table->timestamps();
            $table->unique(['stock_count_id', 'product_variant_id']);
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();               // TR-000001
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('enviado');   // enviado, recibido, cancelado
            $table->unsignedInteger('pairs')->default(0);
            $table->string('notes', 255)->nullable();
            $table->dateTime('received_at')->nullable();
            $table->timestamps();
        });
        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('reservation_items');
        Schema::dropIfExists('reservations');
        Schema::table('stocks', fn (Blueprint $t) => $t->dropColumn('reserved'));
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('cash_movements');
    }
};
