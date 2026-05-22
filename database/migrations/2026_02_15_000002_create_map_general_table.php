<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_general', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('category', ['status_pulang', 'status_perkawinan', 'jenis_kelamin'])->index();
            $table->string('local_code', 50)->index();
            $table->string('local_term')->nullable();
            $table->string('system_code', 20)->index();
            $table->string('system_term', 255)->nullable();
            $table->string('system_display')->default('http://snomed.info/sct');
            $table->timestamps();

            $table->unique(['category', 'local_code', 'system_code'], 'map_gen_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_general');
    }
};
