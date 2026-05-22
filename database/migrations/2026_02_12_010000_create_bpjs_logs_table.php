<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bpjs_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('service')->index();         // 'erm', 'sep', 'vclaim', dll.
            $table->string('method', 10)->nullable();
            $table->text('endpoint')->nullable();
            $table->string('no_rawat')->nullable()->index();
            $table->string('no_sep')->nullable()->index();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending')->index();
            $table->integer('response_status')->nullable();
            $table->decimal('response_time', 8, 2)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('bundle')->nullable();
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('created_at');
            $table->index(['service', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_logs');
    }
};
