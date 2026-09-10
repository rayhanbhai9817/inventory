<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A movement can touch more than one batch (a Stock OUT that spans
     * multiple FIFO batches). This pivot records exactly how many units
     * came from/went to each batch, so every movement is fully traceable
     * back to specific batches.
     */
    public function up(): void
    {
        Schema::create('stock_movement_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('units');

            $table->index(['stock_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_batches');
    }
};
