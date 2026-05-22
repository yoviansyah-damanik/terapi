<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bpjs_erm', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_rawat')->index();
            $table->string('no_sep')->index();
            $table->string('bundle_id');
            $table->tinyInteger('jenis_pelayanan');   // 1=Rawat Inap, 2=Rawat Jalan/IGD
            $table->tinyInteger('bulan');
            $table->smallInteger('tahun');
            $table->string('room_code'); // Kode Poli atau Bangsal
            $table->string('doctor_code');
            $table->string('encounter_type'); // AMB, INT, EMER
            $table->json('bundle');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['bulan', 'tahun']);
            $table->index('jenis_pelayanan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_erm');
    }
};
