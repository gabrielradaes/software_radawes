<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Avísame cuando llegue mi talla": pedidos de clientes desde la página pública. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('size', 10)->nullable();
            $table->string('color', 60)->nullable();
            $table->dateTime('notified_at')->nullable();   // cuando llegó el stock y se creó el seguimiento
            $table->timestamps();
            $table->index(['product_id', 'branch_id', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_requests');
    }
};
