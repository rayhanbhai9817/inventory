<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The business-management expansion adds several new notification types
 * (supplier_added, product_missing_supplier, product_missing_price, ...)
 * and more will likely follow. Rather than keep adding a migration every
 * time a new type is introduced, widen `type` to a plain string — the
 * enum's only purpose was documentation, and the fixed set of values
 * was already becoming a maintenance burden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [
                'low_stock', 'out_of_stock', 'stock_in', 'stock_out',
                'adjustment', 'admin_activity',
            ])->change();
        });
    }
};
