<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // restrict, not nullOnDelete: a category with products attached
            // must not be deletable out from under them (see CategoryController).
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('sku');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('min_stock_level')->default(0);
            // Lifecycle: active (both null) / archived (archived_at set) /
            // trashed (deleted_at set, via SoftDeletes). No separate status
            // enum — these two timestamps are the single source of truth.
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'sku']);
            $table->index(['business_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
