    {{-- Modal AI Smart Mapping Evaluator --}}
    <x-organisms.modal wire:model="showAiModal" title="Review Prediksi AI"
        description="Pilih kandidat SNOMED CT terbaik per baris. Abaikan baris yang salah sasaran." maxWidth="6xl">
        <div class="space-y-6">
            <div class="flex items-center justify-between gap-3">
                {{-- Deep Scan Toggle --}}
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <div
                        class="flex items-center gap-1.5 text-xs font-medium {{ $useDeepScanAi ? 'text-purple-700 dark:text-purple-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                        <flux:icon name="cpu-chip" class="w-3.5 h-3.5" />
                        Deep Scan AI
                    </div>
                    <flux:switch wire:model.live="useDeepScanAi" :disabled="$isAiProcessing" />
                </label>

                @if ($isAiProcessing)
                    <div
                        class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 rounded-full border border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-700/50">
                        <flux:icon.loading class="w-3.5 h-3.5" />
                        <span>Memproses (Sisa {{ count($aiQueue) }} baris)...</span>
                    </div>
                @else
                    <div
                        class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 rounded-full border border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-700/50">
                        <flux:icon name="check-circle" class="w-3.5 h-3.5" />
                        <span>Selesai Analisa AI</span>
                    </div>
                @endif
            </div>

            {{-- Panel Konfirmasi Error (muncul saat proses dijeda) --}}
            @if ($isAiPaused)
                <div
                    class="flex items-start gap-4 rounded-xl border border-red-300 bg-red-50 dark:border-red-800/60 dark:bg-red-900/20 px-5 py-4 shadow-sm">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/50">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-red-800 dark:text-red-300">Proses AI Dijeda — Butuh
                            Konfirmasi</p>
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400 leading-relaxed whitespace-pre-line">
                            {{ $aiPauseReason }}</p>
                        <p class="mt-1.5 text-xs text-red-500 dark:text-red-500">
                            Sisa antrian: <strong>{{ count($aiQueue) }}</strong> item &nbsp;·&nbsp;
                            Gagal: <strong>{{ count($aiFailedQueue) }}</strong> item
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 shrink-0">
                        <x-atoms.button wire:click="resumeAi" variant="primary" size="sm"
                            icon="play">Lanjutkan</x-atoms.button>
                        <x-atoms.button wire:click="stopAi" variant="ghost" size="sm" icon="stop"
                            class="text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30">Hentikan</x-atoms.button>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_2fr] gap-4">
                {{-- Log Window (Kiri) --}}
                <div
                    class="flex flex-col bg-zinc-900 dark:bg-primary-dark-900 rounded-xl border border-zinc-800 dark:border-primary-dark-800 overflow-hidden shadow-inner h-[28rem]">
                    <div class="px-3 py-2 border-b border-zinc-800 flex items-center justify-between shadow-sm">
                        <span class="text-xs font-mono font-medium text-zinc-400">console / progress log</span>
                    </div>
                    <div class="flex-1 p-3 overflow-y-auto font-mono text-xs text-zinc-300 space-y-1.5"
                        x-data="{
                            scrollToBottom() { $el.scrollTop = $el.scrollHeight }
                        }" x-intersect="scrollToBottom()"
                        @process-next-ai.window="setTimeout(() => scrollToBottom(), 50)">
                        @foreach ($aiLogs as $log)
                            <div
                                class="block {{ str_contains($log, '⚠') || str_contains($log, '✗') || str_contains($log, '🟥') ? 'text-red-400' : (str_contains($log, '✓') ? 'text-emerald-400' : (str_contains($log, '📋') || str_contains($log, '⇒') ? 'text-blue-400' : '')) }}">
                                {{ $log }}
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Result Table Multi-Kandidat (Kanan) --}}
                <div
                    class="border rounded-xl border-zinc-200 dark:border-primary-dark-700 shadow-sm overflow-hidden bg-white dark:bg-primary-dark-800 h-[28rem] overflow-y-auto flex flex-col">
                    @forelse($aiResults as $res)
                        @php
                            $candidates = $res['candidates'] ?? [];
                            $selectedCode = $selectedAiResults[$res['icd_code']] ?? null;
                        @endphp
                        <div class="border-b border-zinc-100 dark:border-primary-dark-700/50 last:border-0">
                            {{-- Header baris ICD --}}
                            <div class="px-3 py-2 bg-zinc-50 dark:bg-primary-dark-900/60">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 min-w-0">
                                        <span
                                            class="font-mono text-[10px] font-bold text-zinc-500">{{ $res['icd_code'] }}</span>
                                        <span
                                            class="ml-2 text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ $res['icd_name'] }}</span>
                                    </div>
                                    <div class="shrink-0 text-[9px] text-zinc-400">{{ count($candidates) }} kandidat
                                    </div>
                                    @if ($selectedCode)
                                        <flux:icon name="check-circle" class="w-4 h-4 text-emerald-500 shrink-0" />
                                    @else
                                        <flux:icon name="minus-circle"
                                            class="w-4 h-4 text-zinc-300 dark:text-zinc-600 shrink-0" />
                                    @endif
                                </div>
                                {{-- Interpretasi sumber ICD dari AI Stage 1 --}}
                                @if (!empty($res['interpretation']))
                                    <div class="mt-1.5 flex items-start gap-1.5">
                                        <flux:icon name="information-circle"
                                            class="w-3.5 h-3.5 text-blue-500 shrink-0 mt-0.5" />
                                        <p class="text-[11px] text-blue-700 dark:text-blue-400 leading-snug italic">
                                            {{ $res['interpretation'] }}</p>
                                    </div>
                                @endif
                                @if (!empty($res['primary_medical_term']) && $res['primary_medical_term'] !== $res['icd_name'])
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <flux:icon name="arrow-right" class="w-3 h-3 text-zinc-400 shrink-0" />
                                        <span class="text-[10px] text-zinc-500">Term medis: <span
                                                class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $res['primary_medical_term'] }}</span></span>
                                    </div>
                                @endif
                            </div>

                            {{-- Daftar kandidat --}}
                            @foreach ($candidates as $idx => $candidate)
                                @php
                                    $isSelected = $selectedCode === $candidate['snomed_code'];
                                    $colorClass =
                                        $candidate['score'] >= 75
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                            : ($candidate['score'] >= 45
                                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400');
                                    $isLexicon = ($candidate['source'] ?? '') === 'lexicon';
                                @endphp
                                <label wire:key="cand-{{ $res['icd_code'] }}-{{ $candidate['snomed_code'] }}"
                                    class="flex items-center gap-3 px-4 py-2 cursor-pointer
                                           {{ $isSelected ? 'bg-blue-50 dark:bg-blue-900/20 border-l-2 border-blue-400' : 'hover:bg-zinc-50 dark:hover:bg-primary-dark-700/30 border-l-2 border-transparent' }}
                                           transition-colors">
                                    <input type="radio" name="candidate-{{ $res['icd_code'] }}"
                                        value="{{ $candidate['snomed_code'] }}"
                                        wire:click="$set('selectedAiResults.{{ $res['icd_code'] }}', '{{ $candidate['snomed_code'] }}')"
                                        @checked($isSelected) class="shrink-0 accent-blue-600">
                                    <div class="flex-1 min-w-0">
                                        <div
                                            class="font-mono text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                            {{ $candidate['snomed_code'] }}
                                            @if ($isLexicon)
                                                <span
                                                    class="ml-1 text-[9px] font-semibold text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700/40 rounded px-1 py-0.5">📖
                                                    Lexicon</span>
                                            @elseif($idx === 0)
                                                <span
                                                    class="ml-1 text-[9px] font-semibold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/40 rounded px-1 py-0.5">★
                                                    Terbaik</span>
                                            @endif
                                        </div>
                                        {{-- Preferred Term --}}
                                        <div class="text-xs font-medium text-zinc-700 dark:text-zinc-300 mt-0.5">
                                            {{ $candidate['snomed_term'] }}</div>
                                        {{-- FSN: Fully Specified Name sebagai konteks klinis --}}
                                        @if (!empty($candidate['fsn_term']) && $candidate['fsn_term'] !== $candidate['snomed_term'])
                                            <div
                                                class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5 leading-snug">
                                                <span class="italic">{{ $candidate['fsn_term'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <span
                                        class="shrink-0 inline-flex px-1.5 py-0.5 text-[10px] font-bold rounded {{ $colorClass }}">
                                        {{ $candidate['score'] }}%
                                    </span>
                                </label>
                            @endforeach

                            {{-- Opsi: Abaikan baris ini --}}
                            <label
                                class="flex items-center gap-3 px-4 py-2 cursor-pointer hover:bg-zinc-50 dark:hover:bg-primary-dark-700/30 border-l-2 {{ !$selectedCode ? 'border-zinc-300 bg-zinc-50/50 dark:bg-primary-dark-700/20' : 'border-transparent' }} transition-colors">
                                <input type="radio" name="candidate-{{ $res['icd_code'] }}" value=""
                                    wire:click="$set('selectedAiResults.{{ $res['icd_code'] }}', null)"
                                    @checked(!$selectedCode) class="shrink-0 accent-zinc-400">
                                <span class="text-xs italic text-zinc-400 dark:text-zinc-500">— Abaikan baris ini</span>
                            </label>
                        </div>
                    @empty
                        <div class="flex-1 flex items-center justify-center text-xs text-zinc-400 italic py-8">
                            Belum ada tebakan AI yang ditemukan...
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center gap-3">
                    <div class="text-sm font-medium text-zinc-500">
                        Dipilih: <span
                            class="text-zinc-800 dark:text-zinc-200">{{ count(array_filter($selectedAiResults)) }}</span>
                        dari {{ count($aiResults) }}
                        @if (count($aiFailedQueue) > 0)
                            <span class="ml-2 text-red-500 font-semibold">• {{ count($aiFailedQueue) }} Gagal</span>
                        @endif
                    </div>
                    @if ($useDeepScanAi)
                        <div
                            class="flex items-center gap-1 text-[10px] font-semibold text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800/40 rounded-full px-2 py-0.5">
                            <flux:icon name="cpu-chip" class="w-3 h-3" /> Mode Deep Scan Aktif
                        </div>
                    @endif
                </div>
                <div class="flex gap-2">
                    @if (count($aiFailedQueue) > 0)
                        <x-atoms.button wire:click="retryFailedAi" variant="outline" icon="arrow-path"
                            class="text-amber-600 dark:text-amber-500 border-amber-200 hover:bg-amber-50 dark:border-amber-900/50 dark:hover:bg-amber-900/20"
                            :disabled="$isAiProcessing">Ulangi Gagal</x-atoms.button>
                    @endif
                    <x-atoms.button wire:click="$set('showAiModal', false)" variant="ghost" :disabled="$isAiProcessing">Tutup /
                        Batal</x-atoms.button>
                    <x-atoms.button wire:click="saveAiMapping" variant="primary" icon="check"
                        :disabled="$isAiProcessing">Simpan Terpilih</x-atoms.button>
                </div>
            </div>
        </div>

    </x-organisms.modal>

    @script
        <script>
            /**
             * Intersep Livewire request yang gagal di level network/nginx (HTTP 504, 502, 503).
             * Ini menangani 504 yang TIDAK bisa ditangkap oleh PHP try/catch
             * karena nginx memutus koneksi sebelum PHP bisa merespons.
             */
            Livewire.hook('request', ({
                url,
                options,
                payload,
                respond,
                succeed,
                fail
            }) => {
                fail(({
                    status,
                    content,
                    preventDefault
                }) => {
                    const gatewayErrors = [502, 503, 504];
                    if (gatewayErrors.includes(status)) {
                        preventDefault(); // Cegah Livewire reload / tampilkan error page
                        console.warn(
                            `[AI Mapping] Livewire request failed with HTTP ${status}. Activating pause.`);
                        $wire.call('handleNetworkError', status);
                    }
                });
            });
        </script>
    @endscript
