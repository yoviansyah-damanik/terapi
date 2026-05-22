<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_wa_gateway_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('api_user_id')->nullable()->constrained('api_users')->nullOnDelete();
            $table->string('api_user_name', 150)->nullable();
            $table->string('ip_address', 45)->nullable();

            // Identitas pengiriman
            $table->enum('gateway', ['waha', 'gowa'])->index()->comment('Provider gateway yang digunakan');
            $table->string('action', 50)->index()->comment('send_text, send_image, send_file, send_video, send_audio, send_location, send_contact, send_link, send_poll, check_user, get_status, webhook, broadcast');
            $table->string('phone_number', 30)->nullable()->index();
            $table->string('message_preview', 200)->nullable()->comment('Potongan awal isi pesan');
            $table->string('message_id', 100)->nullable()->comment('ID pesan dari respons gateway');

            // Hasil
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('success')->nullable()->index();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index('created_at');
            $table->index(['gateway', 'created_at']);
            $table->index(['success', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_wa_gateway_logs');
    }
};
