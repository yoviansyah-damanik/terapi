<?php

namespace App\Jobs;

use App\Helpers\ConfigurationHelper;
use App\Models\WaGateway\Gowa\GowaBroadcast;
use App\Services\GowaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGowaBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        public GowaBroadcast $broadcast,
    ) {
        $this->onQueue('messaging');
    }

    public function handle(GowaService $service): void
    {
        $broadcast = $this->broadcast;

        if ($broadcast->status === 'cancelled') {
            return;
        }

        $broadcast->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $delay = (int) ConfigurationHelper::get('gowa.delay', '3');
        $recipients = $broadcast->recipients()->where('status', 'pending')->get();

        foreach ($recipients as $recipient) {
            $broadcast->refresh();
            if ($broadcast->status === 'cancelled') {
                Log::info("GOWA Broadcast #{$broadcast->id} dibatalkan di tengah proses");
                return;
            }

            try {
                $result = $this->sendByType($service, $broadcast, $recipient->phone);

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

                Log::error("GOWA Broadcast #{$broadcast->id} error untuk {$recipient->phone}", [
                    'error' => $e->getMessage(),
                ]);
            }

            $broadcast->recalculateCounts();

            if ($delay > 0) {
                sleep($delay);
            }
        }

        $broadcast->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $broadcast->recalculateCounts();

        Log::info("GOWA Broadcast #{$broadcast->id} selesai", [
            'sent' => $broadcast->sent_count,
            'failed' => $broadcast->failed_count,
        ]);
    }

    /**
     * Kirim pesan berdasarkan tipe broadcast
     */
    private function sendByType(GowaService $service, GowaBroadcast $broadcast, string $phone): array
    {
        $metadata = $broadcast->metadata ?? [];

        return match ($broadcast->type) {
            'image' => $service->sendImage(
                $phone,
                storage_path('app/public/' . $broadcast->file_path),
                $broadcast->message,
            ),
            'file' => $service->sendFile(
                $phone,
                storage_path('app/public/' . $broadcast->file_path),
                $broadcast->message,
            ),
            'video' => $service->sendVideo(
                $phone,
                storage_path('app/public/' . $broadcast->file_path),
                $broadcast->message,
            ),
            'audio' => $service->sendAudio(
                $phone,
                storage_path('app/public/' . $broadcast->file_path),
            ),
            'location' => $service->sendLocation(
                $phone,
                (float) ($metadata['latitude'] ?? 0),
                (float) ($metadata['longitude'] ?? 0),
            ),
            'contact' => $service->sendContact(
                $phone,
                $metadata['contact_name'] ?? '',
                $metadata['contact_phone'] ?? '',
            ),
            'link' => $service->sendLink(
                $phone,
                $metadata['link'] ?? '',
                $broadcast->message,
            ),
            'poll' => $service->sendPoll(
                $phone,
                $metadata['question'] ?? '',
                $metadata['options'] ?? [],
                (int) ($metadata['max_answer'] ?? 1),
            ),
            default => $service->sendMessage($phone, $broadcast->message),
        };
    }

    public function tags(): array
    {
        return ['gowa', 'broadcast:' . $this->broadcast->id];
    }
}
