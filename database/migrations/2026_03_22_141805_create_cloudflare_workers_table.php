<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloudflare_workers', function (Blueprint $table) {
            $table->id();

            // 🔥 owner
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // 🔥 relasi ke CF account
            // $table->foreignId('cloudflare_account_id')
            //     ->constrained()
            //     ->cascadeOnDelete();

            // data worker
            $table->string('worker_name'); // nama internal
            // $table->string('script_name'); // nama di CF (unique)

            $table->string('worker_url')->nullable();

            // $table->longText('script_content')->nullable();

            $table->string('status')->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloudflare_workers');
    }
};