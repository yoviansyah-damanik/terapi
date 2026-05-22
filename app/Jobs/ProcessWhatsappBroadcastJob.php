<?php

namespace App\Jobs;

use App\Helpers\ConfigurationHelper;
use App\Models\WaGateway\Waha\WahaBroadcast;
use App\Services\WahaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsappBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        public WahaBroadcast $broadcast,
    ) {
        $this->onQueue('messaging');
    }

    public function handle(WahaService $service): void
    {
        $broadcast = $this->broadcast;

        if ($broadcast->status === 'cancelled') {
            return;
        }

        $broadcast->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $delay = (int) ConfigurationHelper::get('whatsapp.delay', '3');
        $recipients = $broadcast->recipients()->where('status', 'pending')->get();

        foreach ($recipients as $recipient) {
            // Cek apakah broadcast dibatalkan di tengah proses
            $broadcast->refresh();
            if ($broadcast->status === 'cancelled') {
                Log::info("Broadcast #{$broadcast->id} dibatalkan di tengah proses");
                return;
            }

            try {
                $result = match ($broadcast->type) {
                    'image' => $service->sendImage(
                        $recipient->phone,
                        storage_path('app/public/' . $broadcast->file_path),
                        $broadcast->message,
                    ),
                    'file' => $service->sendFile(
                        $recipient->phone,
                        storage_path('app/public/' . $broadcast->file_path),
                        $broadcast->file_name,
                    ),
                    default => $service->sendText($recipient->phone, $broadcast->message),
                };

                if ($result['success']) {
                    $recipient->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                } else {
                    $recipient->update([
                        'status' => 'failed',
                        'error_message' => $result['message'] ?? $result['error'] ?? 'Gagal mengirim',
                    ]);
                }
            } catch (\Exception $e) {
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                Log::error("Broadcast #{$broadcast->id} error untuk {$recipient->phone}", [
                    'error' => $e->getMessage(),
                ]);
            }

            // Update counter
            $broadcast->recalculateCounts();

            // Delay antar pesan
            if ($delay > 0) {
                sleep($delay);
            }
        }

        $broadcast->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $broadcast->recalculateCounts();

        Log::info("Broadcast #{$broadcast->id} selesai", [
            'sent' => $broadcast->sent_count,
            'failed' => $broadcast->failed_count,
        ]);
    }

    public function tags(): array
    {
        return ['whatsapp', 'broadcast:' . $this->broadcast->id];
    }
}
