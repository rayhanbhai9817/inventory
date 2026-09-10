<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_code');
            $table->unsignedInteger('boxes');
            $table->unsignedInteger('units_per_box');
            // Stored (not computed live) so historical batches remain
            // accurate even if this business's conventions change later.
            $table->unsignedInteger('total_units');
            $table->unsignedInteger('remaining_units');
            $table->enum('status', ['full', 'partial', 'depleted'])->default('full');
            $table->date('received_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'batch_code']);
            // FIFO consumption order: oldest received_at first, id as tiebreaker.
            $table->index(['business_id', 'product_id', 'status', 'received_at', 'id'], 'stock_batches_fifo_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
