<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_rad_specimen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('local_code')->unique();
            $table->string('system_code');
            $table->string('system_term');
            $table->string('system_display')->default('http://snomed.info/sct');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_rad_specimen');
    }
};
