<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_employee_specialty', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('employee_id', 50)->unique()->index(); // kd_dokter atau NIK
            $table->string('specialty_code', 100)->nullable();
            $table->string('specialty_term', 500)->nullable();
            $table->string('specialty_display', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_employee_specialty');
    }
};
