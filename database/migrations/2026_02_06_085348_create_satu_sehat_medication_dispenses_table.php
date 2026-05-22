<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_medication_dispenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index();
            $table->string('local_id')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('medication_ihs')->nullable();
            $table->string('medication_request_ihs')->nullable()->index()->comment('Reference ke MedicationRequest');
            $table->string('performer_ihs')->nullable()->comment('Practitioner yang menyerahkan obat');
            $table->string('status')->default('completed')->comment('preparation, in-progress, cancelled, on-hold, completed, entered-in-error, stopped, declined, unknown');
            $table->decimal('quantity_value', 10, 2)->nullable();
            $table->string('quantity_unit')->nullable();
            $table->timestamp('when_prepared')->nullable();
            $table->timestamp('when_handed_over')->nullable();
            $table->json('dosage_instruction')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->foreign('patient_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_patients')
                ->cascadeOnDelete();

            $table->foreign('encounter_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_encounters')
                ->nullOnDelete();

            $table->foreign('medication_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_medications')
                ->nullOnDelete();

            $table->foreign('medication_request_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_medication_requests')
                ->nullOnDelete();

            $table->foreign('performer_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_ihs', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_medication_dispenses');
    }
};
