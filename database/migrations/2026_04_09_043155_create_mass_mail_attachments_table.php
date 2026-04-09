<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_mail_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained('mass_mail_campaigns')
                ->cascadeOnDelete();

            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_mail_attachments');
    }
};