<?php

namespace App\Services;

use App\Helpers\ConfigurationHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * Mengirimkan pesan ke provider AI aktif.
     * Mengembalikan teks balasan dari AI. Jika responseFormat='json_object', balasan dijamin berformat JSON valid.
     */
    public function sendPrompt(string $systemPrompt, string $userPrompt, string $responseFormat = 'text'): string
    {
        $provider = ConfigurationHelper::get('ai.provider', 'ollama');
        $startTime = microtime(true);
        $result = '';
        $status = 'success';
        $errorMessage = null;

        try {
            $result = match ($provider) {
                'claude' => $this->sendWithClaude($systemPrompt, $userPrompt),
                'openai' => $this->sendWithOpenAI($systemPrompt, $userPrompt, $responseFormat),
                'gemini' => $this->sendWithGemini($systemPrompt, $userPrompt, $responseFormat),
                'grok' => $this->sendWithGrok($systemPrompt, $userPrompt, $responseFormat),
                default => $this->sendWithOllama($systemPrompt, $userPrompt, $responseFormat),
            };
        } catch (\Exception $e) {
            $status = 'error';
            $errorMessage = $e->getMessage();
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        $modelDetail = match ($provider) {
            'claude' => ConfigurationHelper::get('ai.claude_model', 'claude-sonnet-4-6'),
            'openai' => ConfigurationHelper::get('ai.openai_model', 'gpt-4o'),
            'gemini' => ConfigurationHelper::get('ai.gemini_model', 'gemini-2.5-flash'),
            'grok' => ConfigurationHelper::get('ai.grok_model', 'grok-2-latest'),
            default => ConfigurationHelper::get('ai.ollama_model', 'llama3'),
        };

        $baseUrlDetail = match ($provider) {
            'claude' => ConfigurationHelper::get('ai.claude_url', 'https://api.anthropic.com'),
            'openai' => ConfigurationHelper::get('ai.openai_url', 'https://api.openai.com'),
            'gemini' => ConfigurationHelper::get('ai.gemini_url', 'https://generativelanguage.googleapis.com'),
            'grok' => ConfigurationHelper::get('ai.grok_url', 'https://api.x.ai'),
            default => ConfigurationHelper::get('ai.ollama_url', 'http://localhost:11434'),
        };

        try {
            \App\Models\AiLog::create([
                'provider' => $provider,
                'model' => $modelDetail,
                'base_url' => rtrim($baseUrlDetail, '/'),
                'prompt_system' => $systemPrompt,
                'prompt_user' => $userPrompt,
                'response' => $result,
                'response_time_ms' => $durationMs,
                'status' => $status,
                'error_message' => $errorMessage,
            ]);
        } catch (\Exception $e) {
            Log::warning('Gagal menyimpan log AI: ' . $e->getMessage());
        }

        if ($status === 'error') {
            throw new \RuntimeException($errorMessage ?? 'AI Error');
        }

        return $result;
    }

    /**
     * Test koneksi ke provider AI aktif.
     */
    public function testConnection(): array
    {
        $provider = ConfigurationHelper::get('ai.provider', 'ollama');

        return match ($provider) {
            'claude' => $this->testClaude(),
            'openai' => $this->testOpenAI(),
            'gemini' => $this->testGemini(),
            'grok' => $this->testGrok(),
            default => $this->testOllama(),
        };
    }

    /**
     * Ambil daftar model tersedia dari Ollama.
     */
    public function getAvailableModels(): array
    {
        $url = ConfigurationHelper::get('ai.ollama_url', 'http://localhost:11434');

        try {
            $response = Http::timeout(10)->get("{$url}/api/tags");

            if ($response->successful()) {
                return collect($response->json('models', []))
                    ->pluck('name')
                    ->values()
                    ->toArray();
            }

            return [];
        } catch (\Exception $e) {
            Log::warning('Ollama getAvailableModels error: ' . $e->getMessage());
            return [];
        }
    }

    // ------------------------------------------------------------------ //
    //  Provider Implementations
    // ------------------------------------------------------------------ //

    private function sendWithOllama(string $systemPrompt, string $userPrompt, string $responseFormat = 'text'): string
    {
        $url = ConfigurationHelper::get('ai.ollama_url', 'http://localhost:11434');
        $model = ConfigurationHelper::get('ai.ollama_model', 'llama3');

        $payload = [
            'model' => $model,
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        if ($responseFormat === 'json_object' || $responseFormat === 'json') {
            $payload['format'] = 'json';
        }

        $response = Http::connectTimeout(10)->timeout(0)->post("{$url}/api/chat", $payload);

        if (!$response->successful()) {
            $httpStatus = $response->status();
            throw new \RuntimeException("[HTTP:{$httpStatus}] Ollama: " . substr($response->body(), 0, 200));
        }

        return $response->json('message.content', '');
    }

    private function sendWithClaude(string $systemPrompt, string $userPrompt): string
    {
        $url = ConfigurationHelper::get('ai.claude_url', 'https://api.anthropic.com');
        $key = ConfigurationHelper::get('ai.claude_key', '');
        $model = ConfigurationHelper::get('ai.claude_model', 'claude-sonnet-4-6');

        if (empty($key)) {
            throw new \RuntimeException('Claude API key belum dikonfigurasi.');
        }

        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])
            ->post(rtrim($url, '/') . '/v1/messages', [
                'model' => $model,
                'max_tokens' => 2048,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (!$response->successful()) {
            $httpStatus = $response->status();
            throw new \RuntimeException("[HTTP:{$httpStatus}] Claude: " . substr($response->body(), 0, 200));
        }

        return $response->json('content.0.text', '');
    }

    private function sendWithOpenAI(string $systemPrompt, string $userPrompt, string $responseFormat = 'text'): string
    {
        $url = ConfigurationHelper::get('ai.openai_url', 'https://api.openai.com');
        $key = ConfigurationHelper::get('ai.openai_key', '');
        $model = ConfigurationHelper::get('ai.openai_model', 'gpt-4o');

        if (empty($key)) {
            throw new \RuntimeException('OpenAI API key belum dikonfigurasi.');
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        if ($responseFormat === 'json_object') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout(60)
            ->withToken($key)
            ->post(rtrim($url, '/') . '/v1/chat/completions', $payload);

        if (!$response->successful()) {
            $httpStatus = $response->status();
            throw new \RuntimeException("[HTTP:{$httpStatus}] OpenAI: " . substr($response->body(), 0, 200));
        }

        return $response->json('choices.0.message.content', '');
    }

    private function sendWithGemini(string $systemPrompt, string $userPrompt, string $responseFormat = 'text'): string
    {
        $url = ConfigurationHelper::get('ai.gemini_url', 'https://generativelanguage.googleapis.com');
        $key = ConfigurationHelper::get('ai.gemini_key', '');
        $model = ConfigurationHelper::get('ai.gemini_model', 'gemini-2.5-flash');

        if (empty($key)) {
            throw new \RuntimeException('Gemini API key belum dikonfigurasi.');
        }

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userPrompt]]]
            ],
        ];

        if ($responseFormat === 'json_object') {
            $payload['generationConfig'] = ['responseMimeType' => 'application/json'];
        }

        $response = Http::timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $key,
            ])
            ->post(rtrim($url, '/') . "/v1beta/models/{$model}:generateContent", $payload);

        if (!$response->successful()) {
            $httpStatus = $response->status();
            throw new \RuntimeException("[HTTP:{$httpStatus}] Gemini: " . substr($response->body(), 0, 200));
        }

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    private function sendWithGrok(string $systemPrompt, string $userPrompt, string $responseFormat = 'text'): string
    {
        $url = ConfigurationHelper::get('ai.grok_url', 'https://api.x.ai');
        $key = ConfigurationHelper::get('ai.grok_key', '');
        $model = ConfigurationHelper::get('ai.grok_model', 'grok-2-latest');

        if (empty($key)) {
            throw new \RuntimeException('Grok API key belum dikonfigurasi.');
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        if ($responseFormat === 'json_object') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout(60)
            ->withToken($key)
            ->post(rtrim($url, '/') . '/v1/chat/completions', $payload);

        if (!$response->successful()) {
            $httpStatus = $response->status();
            throw new \RuntimeException("[HTTP:{$httpStatus}] Grok: " . substr($response->body(), 0, 200));
        }

        return $response->json('choices.0.message.content', '');
    }

    // ------------------------------------------------------------------ //
    //  Connection Tests
    // ------------------------------------------------------------------ //

    private function testOllama(): array
    {
        $url = ConfigurationHelper::get('ai.ollama_url', 'http://localhost:11434');
        $model = ConfigurationHelper::get('ai.ollama_model', 'llama3');

        try {
            $response = Http::timeout(15)->get("{$url}/api/tags");

            if ($response->successful()) {
                $models = collect($response->json('models', []))->pluck('name')->toArray();
                $found = in_array($model, $models);

                return [
                    'success' => true,
                    'message' => $found
                        ? "Terhubung. Model '{$model}' tersedia."
                        : "Terhubung, tapi model '{$model}' tidak ditemukan. Model tersedia: " . implode(', ', $models),
                    'models' => $models,
                ];
            }

            return ['success' => false, 'message' => "HTTP {$response->status()}: Ollama tidak merespons."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Tidak dapat terhubung ke Ollama: ' . $e->getMessage()];
        }
    }

    private function testClaude(): array
    {
        $url = ConfigurationHelper::get('ai.claude_url', 'https://api.anthropic.com');
        $key = ConfigurationHelper::get('ai.claude_key', '');
        $model = ConfigurationHelper::get('ai.claude_model', 'claude-sonnet-4-6');

        if (empty($key)) {
            return ['success' => false, 'message' => 'API key Claude belum dikonfigurasi.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => '2023-06-01',
                ])
                ->post(rtrim($url, '/') . '/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 10,
                    'messages' => [['role' => 'user', 'content' => 'ping']],
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => "Terhubung ke Claude ({$model})."];
            }

            $error = $response->json('error.message', $response->body());
            return ['success' => false, 'message' => "Claude error: {$error}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Gagal terhubung ke Claude: ' . $e->getMessage()];
        }
    }

    private function testOpenAI(): array
    {
        $url = ConfigurationHelper::get('ai.openai_url', 'https://api.openai.com');
        $key = ConfigurationHelper::get('ai.openai_key', '');
        $model = ConfigurationHelper::get('ai.openai_model', 'gpt-4o');

        if (empty($key)) {
            return ['success' => false, 'message' => 'API key OpenAI belum dikonfigurasi.'];
        }

        try {
            $response = Http::timeout(15)
                ->withToken($key)
                ->get(rtrim($url, '/') . '/v1/models');

            if ($response->successful()) {
                return ['success' => true, 'message' => "Terhubung ke OpenAI (model: {$model})."];
            }

            $error = $response->json('error.message', $response->body());
            return ['success' => false, 'message' => "OpenAI error: {$error}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Gagal terhubung ke OpenAI: ' . $e->getMessage()];
        }
    }

    private function testGemini(): array
    {
        $url = ConfigurationHelper::get('ai.gemini_url', 'https://generativelanguage.googleapis.com');
        $key = ConfigurationHelper::get('ai.gemini_key', '');
        $model = ConfigurationHelper::get('ai.gemini_model', 'gemini-2.5-flash');

        if (empty($key)) {
            return ['success' => false, 'message' => 'API key Gemini belum dikonfigurasi.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $key,
                ])
                ->post(rtrim($url, '/') . "/v1beta/models/{$model}:generateContent", [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => 'ping']]]
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 10
                    ]
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => "Terhubung ke Gemini (model: {$model})."];
            }

            $error = $response->json('error.message', $response->body());
            return ['success' => false, 'message' => "Gemini error: {$error}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Gagal terhubung ke Gemini: ' . $e->getMessage()];
        }
    }

    private function testGrok(): array
    {
        $url = ConfigurationHelper::get('ai.grok_url', 'https://api.x.ai');
        $key = ConfigurationHelper::get('ai.grok_key', '');
        $model = ConfigurationHelper::get('ai.grok_model', 'grok-2-latest');

        if (empty($key)) {
            return ['success' => false, 'message' => 'API key Grok belum dikonfigurasi.'];
        }

        try {
            $response = Http::timeout(15)
                ->withToken($key)
                ->get(rtrim($url, '/') . '/v1/models');

            if ($response->successful()) {
                return ['success' => true, 'message' => "Terhubung ke Grok (model: {$model})."];
            }

            $error = $response->json('error.message', $response->body());
            return ['success' => false, 'message' => "Grok error: {$error}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Gagal terhubung ke Grok: ' . $e->getMessage()];
        }
    }
}
