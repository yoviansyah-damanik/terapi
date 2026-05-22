<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_healthcare_service', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 15); // polyclinic | ward
            $table->string('local_code', 20)->index();
            $table->string('physical_type_code')->nullable();
            $table->string('physical_type_term')->nullable();
            $table->string('physical_type_display')->nullable();
            $table->timestamps();
            $table->unique(['type', 'local_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_healthcare_service');
    }
};
