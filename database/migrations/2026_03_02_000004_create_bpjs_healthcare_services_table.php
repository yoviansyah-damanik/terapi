<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bpjs_healthcare_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');        // 'poliklinik' | 'bangsal'
            $table->string('local_code');  // kd_poli atau kd_bangsal dari SIMRS
            $table->string('name');
            $table->unique(['local_code', 'type']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_healthcare_services');
    }
};
