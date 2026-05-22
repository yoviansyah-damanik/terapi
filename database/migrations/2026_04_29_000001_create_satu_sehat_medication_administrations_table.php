<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satu_sehat_medication_administrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index();
            $table->string('local_id')->nullable()->index();
            $table->string('patient_ihs')->index('ss_ma_patient_idx');
            $table->string('encounter_ihs')->nullable()->index('ss_ma_encounter_idx');
            $table->string('medication_ihs')->nullable()->comment('Referensi ke SatuSehatMedication');
            $table->string('medication_request_ihs')->nullable()->index('ss_ma_request_idx')->comment('Referensi ke MedicationRequest');
            $table->string('performer_ihs')->nullable()->comment('Practitioner yang memberikan obat');
            $table->string('status')->default('completed')
                ->comment('in-progress, not-done, on-hold, completed, entered-in-error, stopped, unknown');
            $table->string('category')->nullable()->comment('inpatient, outpatient, community');
            $table->timestamp('effective_start')->nullable();
            $table->timestamp('effective_end')->nullable();
            $table->string('dosage_route_code')->nullable();
            $table->string('dosage_route_display')->nullable();
            $table->decimal('dosage_dose_value', 10, 2)->nullable();
            $table->string('dosage_dose_unit')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('patient_ihs', 'ss_med_admin_patient_fk')
                ->references('ihs_number')->on('satu_sehat_patients')
                ->cascadeOnDelete();

            $table->foreign('encounter_ihs', 'ss_med_admin_encounter_fk')
                ->references('ihs_number')->on('satu_sehat_encounters')
                ->nullOnDelete();

            $table->foreign('medication_ihs', 'ss_med_admin_medication_fk')
                ->references('ihs_number')->on('satu_sehat_medications')
                ->nullOnDelete();

            $table->foreign('medication_request_ihs', 'ss_med_admin_request_fk')
                ->references('ihs_number')->on('satu_sehat_medication_requests')
                ->nullOnDelete();

            $table->foreign('performer_ihs', 'ss_med_admin_performer_fk')
                ->references('ihs_number')->on('satu_sehat_practitioners')
                ->nullOnDelete();

            $table->index(['patient_ihs', 'status'], 'ss_ma_pat_status_idx');
            $table->index(['encounter_ihs', 'status'], 'ss_ma_enc_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_medication_administrations');
    }
};
