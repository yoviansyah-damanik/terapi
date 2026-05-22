<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_imaging_studies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ImagingStudy resource ID dari Satu Sehat');
            $table->string('local_id')->index()->comment('Identifier lokal: no_rawat-kd_jenis_prw-tgl-jam');
            $table->json('identifier')->nullable()->index();
            $table->string('patient_ihs');
            $table->string('encounter_ihs');
            $table->string('status')->default('available');
            $table->string('modality_code')->nullable()->comment('DICOM modality code, mis: CT, MR, CR, DX');
            $table->string('modality_display')->nullable();
            $table->string('body_site_code')->nullable();
            $table->string('body_site_display')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->jsonb('raw_response')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_imaging_studies');
    }
};
