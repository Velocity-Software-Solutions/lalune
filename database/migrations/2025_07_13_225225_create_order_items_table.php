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
        Schema::disableForeignKeyConstraints();

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // Basic descriptors (snapshot so invoices stay accurate if product changes later)
            $table->string('name');                 // product name at purchase
            $table->string('sku')->nullable();      // optional internal SKU
            $table->char('currency', 3)->default('USD');

            // Stripe references (optional but helpful for audits/reconciliation)
            $table->string('stripe_price_id')->nullable()->index();
            $table->string('stripe_product_id')->nullable()->index();

            // Quantity & amounts (all in cents)
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');   // price per unit (pre-discount, pre-tax)
            $table->unsignedBigInteger('subtotal_cents');     // unit_price_cents * quantity
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');        // subtotal - discount + tax

            // Arbitrary snapshot/metadata for the line (e.g., options, size, color)
            $table->json('snapshot')->nullable();

            $table->timestamps();

            // Helpful indexes
            $table->index(['order_id']);
            $table->index(['product_id']);
        });


        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
