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
            $table->foreignId('user_id')->nullable()->constrained()->on('users')->onDelete('cascade');
            $table->string('full_name')->index();
            $table->string('email')->index();
            $table->string('order_number')->index();
            $table->decimal('total_amount');
            $table->enum('payment_status', ['unpaid', 'paid', 'pending', 'failed', 'refunded'])->default('pending');
            $table->enum('order_status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->text('shipping_address');
            $table->text('billing_address')->nullable();
            $table->string('payment_method');
            $table->text('notes')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained()->on('coupons')->onDelete('cascade');
            $table->foreignId('shipping_option_id')->constrained()->on('shipping_options')->onDelete('cascade');
            $table->timestamps();
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
