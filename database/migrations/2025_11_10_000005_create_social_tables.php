<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Publicaciones del feed (como Facebook/Instagram). branch_id null => visible en todas.
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();          // autor (admin)
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete(); // producto etiquetado
            $table->enum('type', ['foto', 'video', 'texto', 'oferta'])->default('foto');
            $table->string('title', 160)->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['published_at', 'is_pinned']);
        });

        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['imagen', 'video'])->default('imagen');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // "Me gusta" en posts y productos. Visitante anónimo => visitor_token (cookie); usuario => user_id.
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->morphs('likeable');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('visitor_token')->nullable();
            $table->timestamps();
            $table->unique(['likeable_type', 'likeable_id', 'visitor_token'], 'likes_visitor_unique');
            $table->unique(['likeable_type', 'likeable_id', 'user_id'], 'likes_user_unique');
        });

        // Comentarios en posts y productos, con respuestas (parent_id) y moderación.
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('visitor_token')->nullable();
            $table->string('author_name', 100);
            $table->string('author_phone', 30)->nullable();
            $table->text('body');
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
        });

        // Cada clic en "Pedir por WhatsApp" queda registrado: el admin ve qué productos generan interés.
        Schema::create('whatsapp_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('visitor_token')->nullable();
            $table->string('message', 500)->nullable();
            $table->string('source', 40)->default('web');   // producto, feed, flotante
            $table->enum('status', ['nuevo', 'contactado', 'vendido', 'perdido'])->default('nuevo');
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_leads');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('likes');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
    }
};
