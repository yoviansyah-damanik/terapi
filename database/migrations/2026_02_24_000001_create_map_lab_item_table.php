<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_lab_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kd_jenis_prw', 20);
            $table->string('id_template', 20);
            $table->string('system_code', 50)->nullable();
            $table->string('system_term', 500)->nullable();
            $table->string('system_display')->default('http://snomed.info/sct');
            $table->timestamps();

            $table->unique(['kd_jenis_prw', 'id_template']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_lab_item');
    }
};
