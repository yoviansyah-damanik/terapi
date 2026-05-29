<?php

use App\Helpers\ConfigurationHelper;
use App\Services\AiService;
use Livewire\Component;

new class extends Component {
    public string $aiProvider = 'ollama';
    public string $ollamaUrl = '';
    public string $ollamaModel = '';
    public string $claudeUrl = '';
    public string $claudeKey = '';
    public string $claudeModel = '';
    public string $openaiUrl = '';
    public string $openaiKey = '';
    public string $openaiModel = '';
    public string $geminiUrl = '';
    public string $geminiKey = '';
    public string $geminiModel = '';
    public string $grokUrl = '';
    public string $grokKey = '';
    public string $grokModel = '';
    public array $availableModels = [];
    public int $testKey = 1;

    public function mount(): void
    {
        $this->aiProvider = ConfigurationHelper::get('ai.provider', 'ollama');
        $this->ollamaUrl = ConfigurationHelper::get('ai.ollama_url', 'http://localhost:11434');
        $this->ollamaModel = ConfigurationHelper::get('ai.ollama_model', 'llama3');
        $this->claudeUrl = ConfigurationHelper::get('ai.claude_url', 'https://api.anthropic.com');
        $this->claudeKey = ConfigurationHelper::get('ai.claude_key', '');
        $this->claudeModel = ConfigurationHelper::get('ai.claude_model', 'claude-sonnet-4-6');
        $this->openaiUrl = ConfigurationHelper::get('ai.openai_url', 'https://api.openai.com');
        $this->openaiKey = ConfigurationHelper::get('ai.openai_key', '');
        $this->openaiModel = ConfigurationHelper::get('ai.openai_model', 'gpt-4o');
        $this->geminiUrl = ConfigurationHelper::get('ai.gemini_url', 'https://generativelanguage.googleapis.com');
        $this->geminiKey = ConfigurationHelper::get('ai.gemini_key', '');
        $this->geminiModel = ConfigurationHelper::get('ai.gemini_model', 'gemini-2.5-flash');
        $this->grokUrl = ConfigurationHelper::get('ai.grok_url', 'https://api.x.ai');
        $this->grokKey = ConfigurationHelper::get('ai.grok_key', '');
        $this->grokModel = ConfigurationHelper::get('ai.grok_model', 'grok-2-latest');
    }

    public function saveAiSettings(): void
    {
        ConfigurationHelper::set('ai.provider', $this->aiProvider);
        if ($this->aiProvider === 'ollama') {
            ConfigurationHelper::set('ai.ollama_url', $this->ollamaUrl);
            ConfigurationHelper::set('ai.ollama_model', $this->ollamaModel);
        } elseif ($this->aiProvider === 'claude') {
            ConfigurationHelper::set('ai.claude_url', $this->claudeUrl);
            ConfigurationHelper::set('ai.claude_key', $this->claudeKey, encrypted: true);
            ConfigurationHelper::set('ai.claude_model', $this->claudeModel);
        } elseif ($this->aiProvider === 'openai') {
            ConfigurationHelper::set('ai.openai_url', $this->openaiUrl);
            ConfigurationHelper::set('ai.openai_key', $this->openaiKey, encrypted: true);
            ConfigurationHelper::set('ai.openai_model', $this->openaiModel);
        } elseif ($this->aiProvider === 'gemini') {
            ConfigurationHelper::set('ai.gemini_url', $this->geminiUrl);
            ConfigurationHelper::set('ai.gemini_key', $this->geminiKey, encrypted: true);
            ConfigurationHelper::set('ai.gemini_model', $this->geminiModel);
        } elseif ($this->aiProvider === 'grok') {
            ConfigurationHelper::set('ai.grok_url', $this->grokUrl);
            ConfigurationHelper::set('ai.grok_key', $this->grokKey, encrypted: true);
            ConfigurationHelper::set('ai.grok_model', $this->grokModel);
        }

        $this->testKey++;
        $this->dispatch('toast', type: 'success', message: 'Pengaturan AI berhasil disimpan.');
    }

    public function loadOllamaModels(): void
    {
        try {
            $this->availableModels = app(AiService::class)->getAvailableModels();
        } catch (\Exception $e) {
            $this->availableModels = [];
        }
    }
}; ?>

<div>
    
    <x-ui.section-card title="AI Provider">
        <x-slot:header>
            <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">AI Provider</h3>
            <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">
                Digunakan oleh fitur
                <a href="{{ route('terminology.smart-search') }}" wire:navigate
                    class="text-primary-600 dark:text-primary-400 hover:underline">Pencarian Pintar</a>
                untuk terjemahan terminologi klinis.
            </p>
        </x-slot:header>

        <form id="form-ai" wire:submit="saveAiSettings" class="space-y-6">
            <div>
                <flux:label class="mb-3 block">Provider AI</flux:label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    @foreach ([['ollama', 'Ollama', 'Berjalan lokal'], ['claude', 'Claude', 'Anthropic API key'], ['openai', 'OpenAI', 'OpenAI API key'], ['gemini', 'Gemini', 'Google Gemini API key'], ['grok', 'Grok', 'xAI API key']] as [$val, $label, $desc])
                        <x-form.provider-card :value="$val" :label="$label" :description="$desc" :active="$aiProvider === $val"
                            wire:model.live="aiProvider" />
                    @endforeach
                </div>
            </div>
            @if ($aiProvider === 'ollama')
                <hr class="border-zinc-200 dark:border-primary-dark-700">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Pengaturan Ollama</h3>
                        <div class="flex items-center gap-2">
                            <x-atoms.button type="button" size="xs" variant="ghost" icon="arrow-path"
                                wire:click="loadOllamaModels">
                                Muat Daftar Model
                            </x-atoms.button>
                            <span wire:loading wire:target="loadOllamaModels" class="text-xs text-zinc-400">Memuat...</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <flux:label>Base URL</flux:label>
                            <flux:input wire:model="ollamaUrl" placeholder="http://localhost:11434" />
                        </div>
                        <div>
                            <flux:label>Model</flux:label>
                            @if (!empty($availableModels))
                                <flux:select wire:model.live="ollamaModel">
                                    @foreach ($availableModels as $m)
                                        <flux:select.option value="{{ $m }}">{{ $m }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:input wire:model="ollamaModel" placeholder="llama3" />
                            @endif
                        </div>
                    </div>
                </div>
            @endif
            @if ($aiProvider === 'claude')
                <hr class="border-zinc-200 dark:border-primary-dark-700">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300 mb-3">Pengaturan Claude
                        (Anthropic)</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <flux:label>Base URL</flux:label>
                            <flux:input wire:model="claudeUrl" placeholder="https://api.anthropic.com" />
                        </div>
                        <div>
                            <flux:label>API Key <span class="text-xs text-zinc-400 font-normal">(terenkripsi)</span>
                            </flux:label>
                            <flux:input type="password" wire:model="claudeKey" placeholder="sk-ant-..." />
                        </div>
                        <div>
                            <flux:label>Model</flux:label>
                            <flux:input wire:model="claudeModel" placeholder="claude-sonnet-4-6" />
                        </div>
                    </div>
                </div>
            @endif
            @if ($aiProvider === 'openai')
                <hr class="border-zinc-200 dark:border-primary-dark-700">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300 mb-3">Pengaturan OpenAI</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <flux:label>Base URL</flux:label>
                            <flux:input wire:model="openaiUrl" placeholder="https://api.openai.com" />
                        </div>
                        <div>
                            <flux:label>API Key <span class="text-xs text-zinc-400 font-normal">(terenkripsi)</span>
                            </flux:label>
                            <flux:input type="password" wire:model="openaiKey" placeholder="sk-..." />
                        </div>
                        <div>
                            <flux:label>Model</flux:label>
                            <flux:input wire:model="openaiModel" placeholder="gpt-4o" />
                        </div>
                    </div>
                </div>
            @endif
            @if ($aiProvider === 'gemini')
                <hr class="border-zinc-200 dark:border-primary-dark-700">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300 mb-3">Pengaturan Gemini</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <flux:label>Base URL</flux:label>
                            <flux:input wire:model="geminiUrl" placeholder="https://generativelanguage.googleapis.com" />
                        </div>
                        <div>
                            <flux:label>API Key <span class="text-xs text-zinc-400 font-normal">(terenkripsi)</span>
                            </flux:label>
                            <flux:input type="password" wire:model="geminiKey" placeholder="AIzaSy..." />
                        </div>
                        <div>
                            <flux:label>Model</flux:label>
                            <flux:input wire:model="geminiModel" placeholder="gemini-2.5-flash" />
                        </div>
                    </div>
                </div>
            @endif
            @if ($aiProvider === 'grok')
                <hr class="border-zinc-200 dark:border-primary-dark-700">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300 mb-3">Pengaturan Grok (xAI)</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <flux:label>Base URL</flux:label>
                            <flux:input wire:model="grokUrl" placeholder="https://api.x.ai" />
                        </div>
                        <div>
                            <flux:label>API Key <span class="text-xs text-zinc-400 font-normal">(terenkripsi)</span></flux:label>
                            <flux:input type="password" wire:model="grokKey" placeholder="xai-..." />
                        </div>
                        <div>
                            <flux:label>Model</flux:label>
                            <flux:input wire:model="grokModel" placeholder="grok-2-latest" />
                        </div>
                    </div>
                </div>
            @endif
        </form>

        <x-slot:footer>
            <div class="flex items-center gap-3">
                <x-atoms.button form="form-ai" type="submit" variant="primary" icon="check">Simpan</x-atoms.button>
                <span wire:loading wire:target="saveAiSettings"
                    class="text-sm text-zinc-500 dark:text-primary-dark-400">Menyimpan...</span>
                @php
                    $aiTestUrl = match ($aiProvider) {
                        'claude' => rtrim($claudeUrl ?: 'https://api.anthropic.com', '/') . '/v1/models',
                        'openai' => rtrim($openaiUrl ?: 'https://api.openai.com', '/') . '/v1/models',
                        'gemini' => rtrim($geminiUrl ?: 'https://generativelanguage.googleapis.com', '/') . '/v1beta/models/gemini-2.5-flash:generateContent',
                        'grok'   => rtrim($grokUrl ?: 'https://api.x.ai', '/') . '/v1/models',
                        default => rtrim($ollamaUrl ?: 'http://localhost:11434', '/') . '/api/tags',
                    };
                    $aiTestHeaders = match ($aiProvider) {
                        'claude' => ['x-api-key' => $claudeKey, 'anthropic-version' => '2023-06-01'],
                        'openai' => ['Authorization' => 'Bearer ' . $openaiKey],
                        'gemini' => ['Content-Type' => 'application/json', 'x-goog-api-key' => $geminiKey],
                        'grok'   => ['Authorization' => 'Bearer ' . $grokKey],
                        default => [],
                    };
                    $aiTestMethod = match ($aiProvider) {
                        'gemini' => 'POST',
                        default => 'GET',
                    };
                    $aiTestBody = match ($aiProvider) {
                       'gemini' => ['contents' => [['parts' => [['text' => 'ping']]]]],
                       default => [],
                    };
                @endphp
                <livewire:components.connection-result wire:key="ai-test-{{ $testKey }}" :url="$aiTestUrl" :method="$aiTestMethod" :body="$aiTestBody" :headers="$aiTestHeaders"
                    name="connection-ai-{{ $testKey }}" title="Tes Koneksi — AI Provider" label="Tes Koneksi" variant="ghost"
                    icon="signal" />
            </div>
        </x-slot:footer>
    </x-ui.section-card>
</div>
