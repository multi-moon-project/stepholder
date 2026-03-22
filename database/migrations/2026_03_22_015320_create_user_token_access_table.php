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
      Schema::create('user_token_access', function (Blueprint $table) {

    $table->id();

    $table->foreignId('user_id');   // sub-user
    $table->foreignId('token_id');  // token milik owner

    $table->timestamps();

    $table->unique(['user_id', 'token_id']);

    $table->foreign('user_id')
        ->references('id')
        ->on('users')
        ->cascadeOnDelete();

    $table->foreign('token_id')
        ->references('id')
        ->on('tokens')
        ->cascadeOnDelete();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_token_access');
    }
};
