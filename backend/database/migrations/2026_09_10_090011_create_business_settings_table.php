<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('date_format')->default('Y-m-d');
            $table->string('number_format')->default('en-US');
            $table->unsignedInteger('default_min_stock_threshold')->default(10);
            $table->boolean('low_stock_notifications_enabled')->default(true);
            $table->boolean('out_of_stock_notifications_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
