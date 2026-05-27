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
        Schema::create('satu_sehat_medication_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique();
            $table->string('local_id')->unique();
            $table->string('patient_ihs');
            $table->string('encounter_ihs');
            $table->string('medication_ihs');
            $table->string('status')->default('completed');
            $table->string('category')->nullable();
            $table->string('dosage_text')->nullable();
            $table->string('effective_datetime')->nullable();
            $table->boolean('is_vaccine')->default(false)->index();
            $table->json('raw_response')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('patient_ihs');
            $table->index('encounter_ihs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_medication_statements');
    }
};
