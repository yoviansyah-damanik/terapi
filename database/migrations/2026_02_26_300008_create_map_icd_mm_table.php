<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_icd_mm', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->index();
            $table->string('version', 50)->index();
            $table->string('system_code', 20)->index();
            $table->string('system_term', 500)->nullable();
            $table->string('system_display')->default('http://snomed.info/sct');
            $table->timestamps();

            $table->unique(['code', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_icd_mm');
    }
};
