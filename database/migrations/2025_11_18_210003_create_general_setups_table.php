<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_setups', function (Blueprint $table) {
            $table->id();

            // Unique key to identify what this setup is for
            $table->string('key')->unique();

            // Editor content (HTML)
            $table->longText('content')->nullable();

            // Optional background image path (public disk)
            $table->string('background_image')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_setups');
    }
};
