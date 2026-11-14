<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Los clientes pueden crear cuenta en el sitio público (guard "customer").
        Schema::table('customers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('document');   // null => cliente creado por el equipo en una venta
            $table->rememberToken()->after('password');
            $table->timestamp('email_verified_at')->nullable()->after('remember_token');
            $table->string('city', 80)->nullable()->after('email_verified_at');
            $table->date('birthdate')->nullable()->after('city');
            $table->boolean('is_active')->default(true)->after('birthdate');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->uuid('visitor_token')->nullable()->after('last_login_at');   // para heredar sus likes anónimos
        });

        Schema::table('likes', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['likeable_type', 'likeable_id', 'customer_id'], 'likes_customer_unique');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('whatsapp_leads', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('post_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_leads', fn (Blueprint $t) => $t->dropConstrainedForeignId('customer_id'));
        Schema::table('comments', fn (Blueprint $t) => $t->dropConstrainedForeignId('customer_id'));
        Schema::table('likes', function (Blueprint $t) {
            $t->dropUnique('likes_customer_unique');
            $t->dropConstrainedForeignId('customer_id');
        });
        Schema::table('customers', function (Blueprint $t) {
            $t->dropColumn(['password', 'remember_token', 'email_verified_at', 'city', 'birthdate', 'is_active', 'last_login_at', 'visitor_token']);
        });
    }
};
