<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // either (or both) can be NULL to support 1-D stock
            $table->foreignId('color_id')->nullable()->constrained('product_colors')->cascadeOnDelete();
            $table->foreignId('size_id')->nullable()->constrained('product_sizes')->cascadeOnDelete();

            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('product_stocks'); // drops constraints/indexes with it
    }
};
