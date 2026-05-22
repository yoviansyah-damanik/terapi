<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_rad', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('local_code', 20)->unique()->index();
            $table->string('system_code', 20);
            $table->string('system_term', 500)->nullable();
            $table->string('system_display')->default('http://loinc.org');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_rad');
    }
};
