<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hl7_code_systems', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->index();
            $table->string('system_code');
            $table->string('system_term');
            $table->string('system_defenition')->nullable();
            $table->string('system_display')->default('http://terminology.hl7.org/CodeSystem');
            $table->timestamps();

            $table->unique(['type', 'system_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hl7_code_systems');
    }
};
