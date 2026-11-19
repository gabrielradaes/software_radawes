<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Índices para que el stock, el kardex, las ventas y los reportes sigan rápidos con decenas de miles de registros. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $t) {
            $t->index(['branch_id', 'quantity'], 'stocks_branch_quantity_index');
        });
        Schema::table('stock_movements', function (Blueprint $t) {
            $t->index(['stock_id', 'created_at'], 'stock_movements_stock_created_index');
            $t->index(['type', 'created_at'], 'stock_movements_type_created_index');
        });
        Schema::table('sales', function (Blueprint $t) {
            $t->index(['status', 'sold_at'], 'sales_status_sold_index');
            $t->index('sold_at', 'sales_sold_at_index');
        });
        Schema::table('products', function (Blueprint $t) {
            $t->index(['deleted_at', 'name'], 'products_deleted_name_index');
        });
        Schema::table('attribute_values', function (Blueprint $t) {
            $t->index(['attribute_id', 'value'], 'attribute_values_attribute_value_index');
        });
        Schema::table('product_variants', function (Blueprint $t) {
            $t->index(['product_id', 'is_active'], 'product_variants_product_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('stocks', fn (Blueprint $t) => $t->dropIndex('stocks_branch_quantity_index'));
        Schema::table('stock_movements', function (Blueprint $t) {
            $t->dropIndex('stock_movements_stock_created_index');
            $t->dropIndex('stock_movements_type_created_index');
        });
        Schema::table('sales', function (Blueprint $t) {
            $t->dropIndex('sales_status_sold_index');
            $t->dropIndex('sales_sold_at_index');
        });
        Schema::table('products', fn (Blueprint $t) => $t->dropIndex('products_deleted_name_index'));
        Schema::table('attribute_values', fn (Blueprint $t) => $t->dropIndex('attribute_values_attribute_value_index'));
        Schema::table('product_variants', fn (Blueprint $t) => $t->dropIndex('product_variants_product_active_index'));
    }
};
