<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dicom_studies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_rawat', 20)->nullable()->index();
            $table->string('noorder', 50)->nullable()->index();
            $table->string('orthanc_study_id', 64)->nullable();
            $table->string('imaging_study_ihs', 64)->nullable();
            $table->string('study_instance_uid', 64)->nullable();
            $table->string('patient_id', 50)->nullable();
            $table->string('modality', 10)->nullable();
            $table->string('study_description', 255)->nullable();
            $table->date('study_date')->nullable();
            $table->unsignedInteger('series_count')->default(0);
            $table->unsignedInteger('instance_count')->default(0);
            $table->string('ae_title', 64)->nullable()->comment('AE Title target modality');
            $table->enum('status', ['worklist', 'pending', 'received', 'sent', 'error'])->default('pending')
                ->comment('worklist=MWL dikirim ke PACS, pending=menunggu scan, received=gambar diterima, sent=dikirim ke router, error=gagal');
            $table->timestamp('sent_to_router_at')->nullable();
            $table->string('router_job_id', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dicom_studies');
    }
};
