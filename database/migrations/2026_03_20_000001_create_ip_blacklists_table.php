<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_blacklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ip_address', 45)->unique();
            $table->string('reason')->nullable();
            $table->string('blocked_by', 100)->nullable(); // username atau 'system'
            $table->timestamp('expires_at')->nullable();   // null = permanen
            $table->timestamps();

            $table->index('ip_address');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_blacklists');
    }
};
