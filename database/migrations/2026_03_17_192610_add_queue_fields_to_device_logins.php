<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_logins', function (Blueprint $table) {

            $table->string('status')->default('pending')->after('completed');

            $table->integer('interval')->default(5)->after('user_code');

            $table->timestamp('last_polled_at')->nullable();

            $table->timestamp('next_poll_at')->nullable();

            $table->integer('retry_count')->default(0);

            $table->text('last_error')->nullable();

        });
    }

    public function down(): void
    {
        Schema::table('device_logins', function (Blueprint $table) {

            $table->dropColumn([
                'status',
                'interval',
                'last_polled_at',
                'next_poll_at',
                'retry_count',
                'last_error'
            ]);

        });
    }
};