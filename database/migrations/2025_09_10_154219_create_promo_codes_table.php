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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();

            // type of discount: fixed amount, percentage, or free shipping
            $table->enum('discount_type', ['shipping', 'fixed', 'percentage']);

            // value is required for fixed/percent, null for free_shipping
            $table->decimal('value', 8, 2)->nullable();

            // minimum subtotal required to apply
            $table->decimal('min_order_amount', 8, 2)->nullable();

            // usage limit per code (null or 0 = unlimited)
            $table->integer('usage_limit')->nullable();

            $table->integer('used_count')->default(0);


            // when the code expires
            $table->dateTime('expires_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
