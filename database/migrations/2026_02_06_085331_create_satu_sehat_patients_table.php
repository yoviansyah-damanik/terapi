<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satu_sehat_patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat (IHS Number)');
            $table->string('nik', 16)->nullable()->index();
            $table->string('name');
            $table->enum('gender', ['male', 'female', 'other', 'unknown'])->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->json('raw_response')->nullable()->comment('Response lengkap dari API');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['nik', 'birth_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_patients');
    }
};
