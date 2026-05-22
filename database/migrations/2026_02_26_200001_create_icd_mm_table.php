<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icd_mm', function (Blueprint $table) {
            $table->id();
            $table->string('annex', 10)->nullable();          // B1, B2, B3
            $table->string('annex_display', 500)->nullable(); // Label lengkap annex
            $table->string('group_code', 50)->nullable();     // GROUP-1, GROUP-2, dst.
            $table->string('group_display', 500)->nullable(); // Judul group
            $table->string('code', 30);
            $table->string('display', 1000)->nullable();
            $table->string('version', 50);
            $table->timestamps();

            $table->unique(['code', 'version']);
            $table->index(['version', 'annex', 'group_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icd_mm');
    }
};
