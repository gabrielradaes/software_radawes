<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada sucursal es una "tienda" con su propia marca y colores en el sitio público
        Schema::table('branches', function (Blueprint $table) {
            $table->string('brand_name', 60)->nullable()->after('name');        // RadaWes, RyE, RyC
            $table->string('brand_tagline', 120)->nullable()->after('brand_name'); // Shoes Made in Brasil
            $table->string('brand_small', 60)->nullable()->after('brand_tagline'); // texto chico bajo la marca (ej. "RadaWes")
            $table->string('color_primary', 7)->default('#5a3a2a')->after('cover_path');
            $table->string('color_accent', 7)->default('#c8a24a')->after('color_primary');
            $table->string('color_surface', 7)->default('#faf6f1')->after('color_accent');
        });

        // Catálogo de cada sucursal: qué productos se venden en cada tienda
        Schema::create('branch_product', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->primary(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_product');
        Schema::table('branches', fn (Blueprint $t) => $t->dropColumn(['brand_name', 'brand_tagline', 'brand_small', 'color_primary', 'color_accent', 'color_surface']));
    }
};
