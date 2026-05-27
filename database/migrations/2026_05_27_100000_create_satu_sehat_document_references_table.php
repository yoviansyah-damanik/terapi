<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satu_sehat_document_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->nullable()->index();
            $table->string('local_id')->index();
            $table->string('doc_type')->default('prescription');
            $table->string('patient_ihs')->nullable();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('author_ihs')->nullable();
            $table->string('status')->default('current');
            $table->string('doc_status')->default('final');
            $table->json('raw_response')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_document_references');
    }
};
