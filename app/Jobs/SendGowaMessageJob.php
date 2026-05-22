<?php

namespace App\Jobs;

use App\Helpers\ConfigurationHelper;
use App\Models\WaGateway\Gowa\GowaMessage;
use App\Services\GowaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendGowaMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public int $backoff;

    public function __construct(
        public GowaMessage $message,
    ) {
        $this->tries = (int) ConfigurationHelper::get('gowa.tries', '3');
        $this->backoff = (int) ConfigurationHelper::get('gowa.backoff', '10');
        $this->onQueue('messaging');
    }

    public function handle(GowaService $service): void
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
                    $this->message->message,
                ),
                'video' => $service->sendVideo(
                    $this->message->phone,
                    storage_path('app/public/' . $this->message->file_path),
                    $this->message->message,
                ),
                'audio' => $service->sendAudio(
                    $this->message->phone,
                    storage_path('app/public/' . $this->message->file_path),
                ),
                'location' => $service->sendLocation(
                    $this->message->phone,
                    (float) ($this->message->metadata['latitude'] ?? 0),
                    (float) ($this->message->metadata['longitude'] ?? 0),
                ),
                'contact' => $service->sendContact(
                    $this->message->phone,
                    $this->message->metadata['contact_name'] ?? '',
                    $this->message->metadata['contact_phone'] ?? '',
                ),
                'link' => $service->sendLink(
                    $this->message->phone,
                    $this->message->metadata['link'] ?? '',
                    $this->message->message,
                ),
                'poll' => $service->sendPoll(
                    $this->message->phone,
                    $this->message->metadata['question'] ?? '',
                    $this->message->metadata['options'] ?? [],
                    (int) ($this->message->metadata['max_answer'] ?? 1),
                ),
                default => $service->sendMessage(
                    $this->message->phone,
                    $this->message->message,
                ),
            };

            if ($result['success']) {
                $this->message->update([
                    'status' => 'sent',
                    'gowa_message_id' => $result['data']['message_id'] ?? null,
                    'sent_at' => now(),
                ]);

                Log::info("GOWA: Pesan berhasil dikirim", [
                    'message_id' => $this->message->id,
                    'phone' => $this->message->phone,
                    'type' => $this->message->type,
                ]);
            } else {
                $errorMessage = $result['message'] ?? $result['error'] ?? 'Gagal mengirim pesan';

                $this->message->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);

                Log::warning("GOWA: Gagal mengirim pesan", [
                    'message_id' => $this->message->id,
                    'error' => $errorMessage,
                ]);
            }
        } catch (\Exception $e) {
            $this->message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("GOWA: Error saat mengirim pesan", [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function tags(): array
    {
        return ['gowa', 'message:' . $this->message->id];
    }
}
