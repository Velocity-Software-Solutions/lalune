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
        Schema::disableForeignKeyConstraints();

      Schema::create('coupons', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->enum('discount_type', ['fixed', 'percentage']); // Fix here
    $table->decimal('value', 8, 2);
    $table->decimal('min_order_amount', 8, 2)->nullable();
    $table->integer('usage_limit')->nullable();
    $table->dateTime('expires_at');
    $table->boolean('is_active')->default(true);
    $table->timestamps(); // Add created_at and updated_at
});


        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
