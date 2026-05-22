<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simrs_update_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_user_id')->nullable()->constrained('api_users')->nullOnDelete();
            $table->string('api_user_name')->nullable();
            $table->string('ip_address', 45);
            $table->string('host_name', 100)->nullable();
            $table->string('app_name', 100)->nullable();
            $table->string('from_version', 20)->nullable();
            $table->string('to_version', 20)->nullable();
            $table->enum('status', ['success', 'failed', 'rollback'])->default('success');
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simrs_update_reports');
    }
};
