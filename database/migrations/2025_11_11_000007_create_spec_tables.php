<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campos de ficha técnica definidos por categoría principal (Calzado, Motos, Accesorios…)
        Schema::create('spec_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('unit', 20)->nullable();          // cc, kg, cm, L, km/h…
            $table->string('placeholder', 120)->nullable();  // ejemplo para el formulario
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->timestamps();
            $table->unique(['category_id', 'name']);
        });

        // Valor de cada campo para cada producto
        Schema::create('product_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spec_field_id')->constrained()->cascadeOnDelete();
            $table->string('value', 255);
            $table->timestamps();
            $table->unique(['product_id', 'spec_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specs');
        Schema::dropIfExists('spec_fields');
    }
};
