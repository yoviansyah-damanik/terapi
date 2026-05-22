<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('worklists', function (Blueprint $table) {
            $table->string('accession_number')->primary(); // Gabungan noorder dan kd_prw
            $table->string('noorder')->index();
            $table->string('no_rawat')->index();
            $table->string('patient_id')->index();
            $table->string('patient_name');
            $table->date('birth_date')->nullable();
            $table->string('gender', 1)->nullable(); // L / P
            $table->string('modality', 10)->nullable();
            $table->string('ae_title', 50)->nullable();
            $table->string('procedure_desc')->nullable();
            $table->dateTime('scheduled_date')->nullable();
            $table->string('study_instance_uid')->nullable();
            $table->string('orthanc_study_id')->nullable();
            $table->string('imaging_study_ihs')->nullable();
            $table->integer('series_count')->default(0);
            $table->integer('instance_count')->default(0);
            $table->dateTime('sent_to_router_at')->nullable();
            $table->string('router_job_id')->nullable();
            $table->string('status')->default('pending'); // pending, worklist, failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worklists');
    }
};
