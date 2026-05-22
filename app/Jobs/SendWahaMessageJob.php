<?php

namespace App\Jobs;

use App\Helpers\ConfigurationHelper;
use App\Models\WaGateway\Waha\WahaMessage;
use App\Services\WahaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWahaMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public int $backoff;

    public function __construct(
        public WahaMessage $message,
    ) {
        $this->tries = (int) ConfigurationHelper::get('waha.tries', '3');
        $this->backoff = (int) ConfigurationHelper::get('waha.backoff', '10');
        $this->onQueue('messaging');
    }

    public function handle(WahaService $service): void
    {
        try {
            $result = match ($this->message->type) {
                'image' => $service->sendImage(
                    $this->message->phone,
                    storage_path('app/public/' . $this->message->file_path),
                    $this->message->message,
                ),
                'file' => $service->sendFile(
                    $this->message->phone,
                    storage_path('app/public/' . $this->message->file_path),
                    $this->message->file_name,
                ),
                default => $service->sendText(
                    $this->message->phone,
                    $this->message->message,
                ),
            };

            if ($result['success']) {
                $this->message->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                Log::info("WhatsApp: Pesan berhasil dikirim", [
                    'message_id' => $this->message->id,
                    'phone' => $this->message->phone,
                ]);
            } else {
                $errorMessage = $result['message'] ?? $result['error'] ?? 'Gagal mengirim pesan';

                $this->message->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);

                Log::warning("WhatsApp: Gagal mengirim pesan", [
                    'message_id' => $this->message->id,
                    'error' => $errorMessage,
                ]);
            }
        } catch (\Exception $e) {
            $this->message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("WhatsApp: Error saat mengirim pesan", [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function tags(): array
    {
        return ['whatsapp', 'message:' . $this->message->id];
    }
}
