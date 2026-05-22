<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dicom_modalities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('router_id')->nullable()->constrained('dicom_routers')->nullOnDelete();
            $table->string('ae_title', 16)->nullable()->unique();
            $table->string('description', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->integer('port')->nullable();
            $table->string('modality_type', 10)->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->boolean('allow_worklist')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dicom_modalities');
    }
};
