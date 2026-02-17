<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // either (or both) can be NULL to support 1-D stock
            $table->foreignId('color_id')->nullable()->constrained('product_colors')->cascadeOnDelete();
            $table->foreignId('size_id')->nullable()->constrained('product_sizes')->cascadeOnDelete();

            $table->decimal('price')->nullable();
            $table->decimal('discounted_price')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'color_id', 'size_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
