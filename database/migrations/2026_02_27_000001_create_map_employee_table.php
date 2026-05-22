<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_employee', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('employee_id', 50)->unique()->index();
            $table->string('system_code', 20)->index();
            $table->string('system_term', 500)->nullable();
            $table->string('system_display')->default('http://snomed.info/sct');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_employee');
    }
};
