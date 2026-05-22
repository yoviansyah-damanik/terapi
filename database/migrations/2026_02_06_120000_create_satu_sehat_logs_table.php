<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satu_sehat_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('resource_type', 50)->index();
            $table->string('action', 20)->index();
            $table->string('method', 10);
            $table->text('endpoint');
            $table->json('request_params')->nullable();
            $table->json('request_body')->nullable();
            $table->integer('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->string('ihs_number')->nullable()->index();
            $table->string('patient_nik')->nullable()->index();
            $table->decimal('response_time', 8, 2)->nullable();
            $table->boolean('is_success')->default(false)->index();
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('created_at');
            $table->index(['resource_type', 'action']);
            $table->index(['is_success', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_logs');
    }
};
