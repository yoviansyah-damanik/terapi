<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gowa_broadcasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('message')->nullable();
            $table->string('type', 20)->default('text');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->enum('status', ['draft', 'processing', 'completed', 'cancelled'])->default('draft');
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gowa_broadcasts');
    }
};
