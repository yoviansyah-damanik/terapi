<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bpjs_procedures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');        // 'ralan' | 'ranap' | 'lab' | 'item_lab' | 'rad'
            $table->string('local_code');  // kd_jenis_prw atau id_template
            $table->string('name');
            $table->unique(['type', 'local_code']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_procedures');
    }
};
