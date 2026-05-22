<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider');
            $table->string('model');
            $table->string('base_url')->nullable();
            $table->longText('prompt_system')->nullable();
            $table->longText('prompt_user')->nullable();
            $table->longText('response')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->string('status', 50)->default('success'); // success / error
            $table->longText('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};
