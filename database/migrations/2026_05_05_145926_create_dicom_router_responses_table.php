<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dicom_router_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dicom_study_id')->nullable()->constrained('dicom_studies')->nullOnDelete();
            $table->string('accession_number', 100)->nullable()->index();
            $table->string('imaging_study_ihs', 64)->nullable();
            $table->string('study_instance_uid', 128)->nullable();
            $table->string('stage', 50)->nullable();
            $table->boolean('status')->default(false);
            $table->string('message', 255)->nullable();
            $table->json('errors')->nullable();
            $table->json('raw_payload');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dicom_router_responses');
    }
};
