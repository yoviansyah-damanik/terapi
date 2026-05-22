<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_specimens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index();
            $table->string('local_id')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('service_request_ihs')->nullable()->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('status')->default('available')->comment('available, unavailable, unsatisfactory, entered-in-error');
            $table->string('type_code')->nullable()->comment('SNOMED CT code');
            $table->string('type_display')->nullable();
            $table->timestamp('collected_datetime')->nullable();
            $table->string('collector_ihs')->nullable();
            $table->timestamp('received_time')->nullable();
            $table->json('container')->nullable();
            $table->text('note')->nullable();
            $table->json('raw_response')->nullable();
            $table->foreign('patient_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_patients')
                ->cascadeOnDelete();

            $table->foreign('service_request_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_service_requests')
                ->nullOnDelete();

            $table->foreign('encounter_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_encounters')
                ->nullOnDelete();

            $table->foreign('collector_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_specimens');
    }
};
