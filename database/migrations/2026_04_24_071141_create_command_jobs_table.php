<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('command_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('file');
            $table->longText('output')->nullable();
            $table->longText('error')->nullable();
            $table->string('status')->default('pending'); // pending, running, success, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_jobs');
    }
};