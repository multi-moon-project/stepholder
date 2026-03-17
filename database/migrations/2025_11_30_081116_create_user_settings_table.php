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
        Schema::create('user_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('key_link')->nullable();
    $table->string('telegram_id_1')->nullable();
    $table->string('telegram_id_2')->nullable();

    $table->string('telegram_bot_1')->nullable();
    $table->string('telegram_bot_2')->nullable();

    $table->enum('subscription_status', ['active', 'expired'])->default('expired');
    $table->date('subscription_until')->nullable();

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
