<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_encounters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('local_id')->nullable()->index()->comment('ID lokal dari sistem');
            $table->enum('status', ['planned', 'arrived', 'triaged', 'in-progress', 'onleave', 'finished', 'cancelled', 'entered-in-error', 'unknown'])->default('arrived');
            $table->string('class')->default('AMB')->comment('AMB, IMP, EMER, etc');
            $table->string('patient_ihs')->index()->comment('Patient IHS number');
            $table->string('patient_name')->nullable();
            $table->string('practitioner_ihs')->nullable()->comment('Practitioner IHS number');
            $table->string('practitioner_name')->nullable();
            $table->string('location_ihs')->nullable()->comment('Location IHS number');
            $table->string('location_name')->nullable();
            $table->string('service_provider')->nullable()->comment('Organization IHS number');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->json('diagnosis')->nullable();
            $table->json('status_history')->nullable();
            $table->json('raw_response')->nullable();
            $table->foreign('patient_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_patients')
                ->cascadeOnDelete();

            $table->foreign('practitioner_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();

            $table->foreign('location_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_locations')
                ->nullOnDelete();


            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['patient_ihs', 'status']);
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_encounters');
    }
};
