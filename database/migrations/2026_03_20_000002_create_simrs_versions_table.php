<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simrs_versions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['main', 'launcher'])->default('main');
            $table->string('version', 20);
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('released_at');
            $table->timestamps();

            $table->unique(['type', 'version']);
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simrs_versions');
    }
};
