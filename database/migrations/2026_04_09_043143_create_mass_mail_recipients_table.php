<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_mail_recipients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained('mass_mail_campaigns')
                ->cascadeOnDelete();

            $table->string('email');
            $table->string('status')->default('pending'); // pending, processing, sent, failed
            $table->unsignedInteger('attempts')->default(0);

            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_mail_recipients');
    }
};