<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_service_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index();
            $table->string('local_id')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('requester_ihs')->nullable()->comment('Practitioner yang membuat permintaan');
            $table->string('status')->default('active')->comment('draft, active, on-hold, revoked, completed, entered-in-error, unknown');
            $table->string('intent')->default('order')->comment('proposal, plan, directive, order, original-order, reflex-order, filler-order, instance-order, option');
            $table->string('priority')->default('routine')->comment('routine, urgent, asap, stat');
            $table->string('category')->nullable()->comment('SNOMED CT code untuk kategori');
            $table->string('code')->nullable()->index()->comment('LOINC code');
            $table->string('code_display')->nullable();
            $table->timestamp('authored_on')->nullable();
            $table->timestamp('occurrence_datetime')->nullable();
            $table->string('reason_code')->nullable()->comment('ICD-10 code');
            $table->string('performer_type')->nullable();
            $table->string('location_code')->nullable();
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

            $table->foreign('requester_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['patient_ihs', 'status']);
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_service_requests');
    }
};
