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
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();

            // variant row (product_stock) OR product-level stock (products)
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_stock_id')->nullable()->constrained('product_stocks')->cascadeOnDelete();

            $table->unsignedInteger('quantity');         // reserved quantity
            $table->string('session_key', 100);     // Laravel session id (or other token)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('status')->default(true)->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            // Helpful indexes
            $table->index(['product_stock_id', 'is_active', 'expires_at']);
            $table->index(['product_id', 'is_active', 'expires_at']);
            $table->index(['session_key', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
