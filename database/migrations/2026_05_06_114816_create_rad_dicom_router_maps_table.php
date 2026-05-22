<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('map_rad_dicom_router', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('local_code')->unique(); // kd_jenis_prw
            $table->foreignUuid('router_id')->nullable()->constrained('dicom_routers')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_rad_dicom_router');
    }
};
