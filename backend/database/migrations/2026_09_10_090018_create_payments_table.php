<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // Polymorphic: the Purchase or Sale this payment is applied to.
            $table->morphs('payable');
            $table->enum('direction', ['in', 'out']);
            $table->decimal('amount', 15, 4);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'card', 'mobile_money', 'other'])->default('cash');
            $table->date('payment_date');
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
