<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_security_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50);                          // rate_limited | oversized_request | anomaly_high_failure | anomaly_high_volume | anomaly_brute_force
            $table->string('ip_address', 45);
            $table->string('method', 10)->nullable();
            $table->string('path')->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignUuid('api_user_id')->nullable()->constrained('api_users')->nullOnDelete();
            $table->string('api_user_name')->nullable();
            $table->json('detail')->nullable();                  // context tambahan: jumlah hit, threshold, dll
            $table->timestamp('resolved_at')->nullable();        // null = masih aktif
            $table->timestamps();

            $table->index('type');
            $table->index('ip_address');
            $table->index('created_at');
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_security_logs');
    }
};
