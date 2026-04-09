<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_mail_campaigns', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('token_id');

            $table->string('name')->nullable();
            $table->string('subject');
            $table->longText('body');

            $table->string('body_mode')->default('editor'); // editor / html
            $table->string('status')->default('queued'); // queued, processing, completed, failed, paused

            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['token_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_mail_campaigns');
    }
};