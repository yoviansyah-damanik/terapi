<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_immunizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('local_id')->nullable()->index();
            $table->json('identifier')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('performer_ihs')->nullable();
            $table->string('location_ihs')->nullable();
            $table->string('status')->default('completed')->comment('completed, entered-in-error, not-done');
            $table->string('vaccine_code')->nullable()->index()->comment('KFA code');
            $table->string('vaccine_display')->nullable();
            $table->timestamp('occurrence_datetime')->nullable();
            $table->string('lot_number')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('site')->nullable();
            $table->string('route')->nullable();
            $table->decimal('dose_quantity', 10, 2)->nullable();
            $table->string('dose_unit')->nullable();
            $table->integer('dose_number')->nullable();
            $table->string('reason_code')->nullable();
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

            $table->foreign('performer_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();

            $table->foreign('location_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_locations')
                ->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['patient_ihs', 'vaccine_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_immunizations');
    }
};
