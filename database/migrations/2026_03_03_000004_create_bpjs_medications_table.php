<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bpjs_medications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('local_code')->unique();  // kode_brng dari SIMRS
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_medications');
    }
};
