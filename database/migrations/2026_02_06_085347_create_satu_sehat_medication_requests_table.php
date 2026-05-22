<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_medication_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index()->comment('Nomor resep');
            $table->string('local_id')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('medication_ihs')->nullable()->index();
            $table->string('requester_ihs')->nullable()->comment('Practitioner yang membuat resep');
            $table->string('status')->default('active')->comment('active, on-hold, cancelled, completed, entered-in-error, stopped, draft, unknown');
            $table->string('intent')->default('order')->comment('proposal, plan, order, original-order, reflex-order, filler-order, instance-order, option');
            $table->timestamp('authored_on')->nullable();
            $table->json('dosage_instruction')->nullable();
            $table->json('dispense_request')->nullable();
            $table->string('reason_code')->nullable()->comment('ICD-10 code');
            $table->text('note')->nullable();
            $table->json('raw_response')->nullable();
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

            $table->foreign('requester_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['patient_ihs', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_medication_requests');
    }
};
