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

        Schema::create('shipping_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar');
            $table->decimal('price', 8, 2);
            $table->string('delivery_time');
            $table->text('description')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable()->index();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        Schema::create('shipping_option_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_option_id')
                ->constrained('shipping_options')
                ->onDelete('cascade');
            $table->string('city')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_options');
    }
};