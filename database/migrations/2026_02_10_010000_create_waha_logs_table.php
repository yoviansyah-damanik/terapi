<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('waha_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('direction', ['outgoing', 'incoming']);
            $table->string('phone')->nullable();
            $table->string('type', 20)->default('text');
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['direction', 'created_at']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waha_logs');
    }
};
