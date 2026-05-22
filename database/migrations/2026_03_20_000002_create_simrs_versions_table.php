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
            $table->string('version', 20)->unique();
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('released_at');
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simrs_versions');
    }
};
