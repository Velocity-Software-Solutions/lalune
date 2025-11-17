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
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string("author_name");
            $table->string("author_email")->nullable();
            // rating in 0.5 steps (0.5 … 5.0)
            $table->decimal('rating', 2, 1); // e.g. 4.5
            $table->text('comment')->nullable();
            $table->string('image_path')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->index();
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
