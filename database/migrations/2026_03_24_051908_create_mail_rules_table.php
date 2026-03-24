<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mail_rules', function (Blueprint $table) {
            $table->id();

            // 🔥 RELASI KE TOKENS
            $table->foreignId('token_id')
                  ->constrained('tokens')
                  ->cascadeOnDelete();

            // BASIC
            $table->string('name');

            // CONDITION
            $table->string('condition_type');   // senderContains / subjectContains
            $table->string('condition_value');

            // ACTIONS
            $table->boolean('action_delete')->default(false);
            $table->boolean('action_read')->default(false);
            $table->string('action_folder')->nullable(); // folderId

            // CONTROL
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);

            $table->timestamps();

            // 🔥 INDEX (biar cepat)
            $table->index('token_id');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_rules');
    }
};