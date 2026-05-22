<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mariadb';

    public function up(): void
    {
        Schema::connection('mariadb')->create('kyc_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('agent_name');
            $table->string('agent_nik', 20);
            $table->string('kyc_type', 20)->index();
            $table->string('patient_nik', 20)->nullable()->index();
            $table->string('patient_name')->nullable();
            $table->text('kyc_url')->nullable();
            $table->string('challenge_code', 10)->nullable();
            $table->string('ihs_number')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_success')->default(false)->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('mariadb')->dropIfExists('kyc_logs');
    }
};
