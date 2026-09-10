<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only reference price ledger — deliberately ONE table, not a
     * separate "current price" table plus a "history" table: every price
     * change is a new row, and "current price" is simply the row with the
     * latest effective_date (see ProductPrice::current()). This avoids a
     * cached "current" value drifting out of sync with its own history,
     * the same principle used for inventory totals elsewhere in this app.
     *
     * Administrative reference data only — never read by InventoryService
     * or anything in the FIFO engine. See docs/ARCHITECTURE.md.
     */
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 4);
            $table->string('currency', 3)->default('USD');
            $table->date('effective_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');

            $table->index(['business_id', 'product_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
