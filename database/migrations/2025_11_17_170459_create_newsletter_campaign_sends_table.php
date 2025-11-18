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
        Schema::create('newsletter_campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('newsletter_campaigns')->onDelete('cascade');
            $table->foreignId('subscriber_id')->constrained('newsletter_subscribers')->onDelete('cascade');

            $table->enum('status', ['pending', 'sent', 'failed','scheduled'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaign_sends');
    }
};
