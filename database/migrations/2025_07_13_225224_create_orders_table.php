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

Schema::create('orders', function (Blueprint $table) {
    $table->id();

    // Relations
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
    $table->foreignId('coupon_id')->nullable()->constrained()->onDelete('set null');
    $table->foreignId('shipping_option_id')->nullable()->constrained()->onDelete('set null');

    // Identifiers
    $table->string('order_number')->unique();
    $table->string('stripe_session_id')->nullable()->unique();
    $table->string('stripe_payment_intent')->nullable()->unique();
    $table->string('stripe_customer_id')->nullable()->index();

    // Customer
    $table->string('full_name')->index();
    $table->string('email')->index();
    $table->string('phone')->nullable();

    // Money (store in cents for accuracy) + currency
    $table->char('currency', 3)->default('USD'); // e.g., 'USD', 'CAD', 'AED'
    $table->unsignedBigInteger('subtotal_cents');
    $table->unsignedBigInteger('discount_cents')->default(0);
    $table->unsignedBigInteger('shipping_cents')->default(0);
    $table->unsignedBigInteger('tax_cents')->default(0);
    $table->unsignedBigInteger('total_cents');

    // Status
    $table->enum('payment_status', ['unpaid','paid','pending','failed','refunded'])->default('pending');
    $table->enum('order_status', ['pending','processing','shipped','delivered','cancelled'])->default('pending');
    $table->string('payment_method')->default('stripe_checkout');

    // Status timestamps
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('receipt_emailed_at')->nullable();
    $table->timestamp('shipped_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();

    // Addresses (structured)
    $table->json('shipping_address_json');
    $table->json('billing_address_json')->nullable();

    // Extras
    $table->string('coupon_code')->nullable();
    $table->string('ip_address', 45)->nullable(); // IPv4/IPv6
    $table->text('user_agent')->nullable();
    $table->json('snapshot')->nullable(); // cart/items snapshot at purchase time
    $table->json('metadata')->nullable();
    $table->text('notes')->nullable();

    $table->timestamps();
    $table->softDeletes();

    // Helpful compound index for queries
    $table->index(['user_id', 'created_at']);
});

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
