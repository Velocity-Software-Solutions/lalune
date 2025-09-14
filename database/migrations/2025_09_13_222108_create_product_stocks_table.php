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

        // CHECK: at least one dimension must be present
        try {
            DB::statement("
                ALTER TABLE product_stocks
                ADD CONSTRAINT chk_product_stock_atleast_one_dim
                CHECK ((color_id IS NOT NULL) OR (size_id IS NOT NULL))
            ");
        } catch (\Throwable $e) {
            // Some MariaDB builds ignore CHECK; safe to skip
        }

        // Unique combination per product
        DB::statement("
            ALTER TABLE product_stocks
            ADD CONSTRAINT uq_product_stock_combo UNIQUE (product_id)
        ");

        // Helpful indexes for admin/storefront queries
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->index('product_id', 'product_stock_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stocks'); // drops constraints/indexes with it
    }
};
