<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_batches', function (Blueprint $table) {
            // Nullable: not every batch has a formal supplier (manual
            // stock-in without one on file, or an adjustment-created
            // batch). Never required by the FIFO engine itself.
            $table->foreignId('supplier_id')->nullable()->after('product_id')
                ->constrained()->restrictOnDelete();
            $table->index(['business_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
