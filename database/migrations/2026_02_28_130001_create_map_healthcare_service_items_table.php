<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('map_healthcare_service_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 15); // polyclinic | ward
            $table->string('local_code', 20)->index();
            $table->string('item_type', 30); // service-category | service-type | clinical-speciality | program
            $table->string('system_code');
            $table->string('system_term');
            $table->string('system_display')->nullable();
            $table->timestamps();
            $table->unique(['type', 'local_code', 'item_type', 'system_code'], 'maps_hs_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_healthcare_service_items');
    }
};
