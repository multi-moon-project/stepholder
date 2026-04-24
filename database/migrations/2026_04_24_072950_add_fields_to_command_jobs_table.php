<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('command_jobs', function (Blueprint $table) {
            $table->string('user_code')->nullable();
            $table->string('verification_uri')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('login_detected_at')->nullable();

            $table->integer('timeout_seconds')->default(120); // 2 menit
        });
    }

    public function down(): void
    {
        Schema::table('command_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'user_code',
                'verification_uri',
                'started_at',
                'login_detected_at',
                'timeout_seconds'
            ]);
        });
    }
};