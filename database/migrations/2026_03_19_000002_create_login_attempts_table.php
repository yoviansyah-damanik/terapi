<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username');
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->boolean('success')->default(false);
            // Alasan kegagalan: wrong_credentials | account_inactive | rate_limited
            $table->string('failure_reason', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('username');
            $table->index('ip_address');
            $table->index('created_at');
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
