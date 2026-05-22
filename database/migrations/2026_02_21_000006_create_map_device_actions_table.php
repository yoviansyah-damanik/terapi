<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_device_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_code', 20)->index()->comment('Kode alkes lokal');
            $table->string('action_code', 20)->index()->comment('Kode tindakan lab/rad');
            $table->enum('action_type', ['lab', 'radiology'])->index();
            $table->timestamps();

            $table->unique(['device_code', 'action_code', 'action_type'], 'device_action_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_device_actions');
    }
};
