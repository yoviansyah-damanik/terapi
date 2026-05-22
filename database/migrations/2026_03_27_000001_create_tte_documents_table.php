<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tte_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('source', 10)->index()->comment('api, simulation');
            $table->string('action', 10)->index()->comment('sign_pdf, seal_pdf');
            $table->string('nik', 20)->nullable()->index();
            $table->string('mode', 15)->nullable()->comment('tag, coordinate, invisible');
            $table->unsignedTinyInteger('file_count')->default(1);
            $table->json('signed_files')->nullable()->comment('Array path di disk tte_signed');

            $table->foreignUuid('api_user_id')->nullable()->constrained('api_users')->nullOnDelete();
            $table->string('api_user_name', 150)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();

            $table->timestamps();

            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tte_documents');
    }
};
