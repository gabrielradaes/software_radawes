<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - audit_logs: quién cambió precios, anuló ventas, ajustó usuarios… (auditoría)
 * - stock_movements.type pasa de ENUM a texto para nuevos tipos (compra, devolucion, reserva, inventario)
 * - Costos: products.cost (último costo), sale_items.unit_cost (costo al momento de vender → utilidad)
 * - Proveedores, compras (notas de ingreso) y curvas de tallas
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);                 // creado, actualizado, eliminado, anulada…
            $table->nullableMorphs('subject');
            $table->string('description', 255);
            $table->json('changes')->nullable();          // {campo: [antes, después]}
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('type', 20)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->nullable()->after('compare_at_price');
        });
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->nullable()->after('unit_price');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('contact', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('country', 80)->nullable()->default('Brasil');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();               // ING-CA-000001
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 60)->nullable();
            $table->date('purchased_at');
            $table->unsignedInteger('pairs')->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('size_curves', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->timestamps();
        });

        Schema::create('size_curve_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_curve_id')->constrained()->cascadeOnDelete();
            $table->string('size', 10);
            $table->unsignedSmallInteger('quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('size_curve_items');
        Schema::dropIfExists('size_curves');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
        Schema::table('sale_items', fn (Blueprint $t) => $t->dropColumn('unit_cost'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('cost'));
        Schema::dropIfExists('audit_logs');
    }
};
