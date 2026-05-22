<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_user_id')->nullable()->constrained('api_users')->nullOnDelete();
            $table->string('api_user_name')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->string('scope')->nullable();
            $table->string('query_string')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->json('request_body')->nullable();
            $table->smallInteger('response_status')->unsigned();
            $table->integer('response_time_ms')->unsigned();
            $table->json('response_body')->nullable();
            $table->json('request_headers')->nullable();
            $table->timestamps();

            $table->index('api_user_id');
            $table->index('scope');
            $table->index('response_status');
            $table->index('created_at');
            $table->index(['response_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
