<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_icd10', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('icd10_code', 20)->index();
            $table->string('system_code', 20)->index();
            $table->string('system_term', 255)->nullable();
            $table->string('system_display')->default('http://snomed.info/sct');
            $table->timestamps();

            $table->unique(['icd10_code', 'system_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_icd10');
    }
};
