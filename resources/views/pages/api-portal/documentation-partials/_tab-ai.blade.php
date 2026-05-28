{{-- ==================== AI PROVIDER — KIRIM PROMPT ==================== --}}
@if ($activeSection === 'ai-prompt')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['ai'] }}/ai/prompt</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Kirim Prompt ke AI Provider</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengirimkan prompt ke AI Provider yang aktif sesuai konfigurasi sistem. Provider yang didukung:
            <strong>Ollama</strong>, <strong>Claude</strong>, <strong>OpenAI</strong>, <strong>Gemini</strong>, dan <strong>Grok</strong>.
            Provider aktif dan model yang digunakan ditentukan dari pengaturan sistem — tidak dapat diubah per-request.
        </p>

        {{-- Provider Info --}}
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-5 gap-2">
            @foreach ([
                ['Ollama', 'bg-zinc-100 dark:bg-primary-dark-700 text-zinc-700 dark:text-primary-dark-200', 'llama3'],
                ['Claude', 'bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-300', 'claude-sonnet-4-6'],
                ['OpenAI', 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300', 'gpt-4o'],
                ['Gemini', 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300', 'gemini-2.5-flash'],
                ['Grok', 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300', 'grok-2-latest'],
            ] as [$name, $cls, $default])
                <div class="px-3 py-2 rounded-lg {{ $cls }} text-center">
                    <p class="text-xs font-bold">{{ $name }}</p>
                    <p class="text-[10px] font-mono mt-0.5 opacity-70">default: {{ $default }}</p>
                </div>
            @endforeach
        </div>

        {{-- Scope --}}
        <div class="mb-4 flex items-center gap-2 p-3 rounded-lg bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-800">
            <flux:icon name="shield-check" class="w-4 h-4 text-cyan-600 dark:text-cyan-400 shrink-0" />
            <span class="text-sm text-cyan-800 dark:text-cyan-300">
                Scope yang dibutuhkan: <strong>ai</strong>
            </span>
        </div>

        <div class="space-y-6">
            {{-- Request Headers --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Request Headers</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Header</x-atoms.table-heading>
                        <x-atoms.table-heading>Nilai</x-atoms.table-heading>
                    </x-slot:headings>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">Authorization</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">Bearer &lt;token&gt;</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">Content-Type</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">application/json</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">Accept</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">application/json</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Request Body --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Request Body</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">user_prompt</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Teks instruksi atau pertanyaan yang dikirim ke AI.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">system_prompt</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">
                            Instruksi perilaku AI (system context). Default:
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">Berikan respon yang singkat dan padat.</code>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">format</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">
                            Format balasan. Nilai yang diizinkan:
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">text</code> (default),
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">json</code>,
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">json_object</code>.
                            Saat menggunakan format JSON, sertakan perintah dalam <code class="text-xs">user_prompt</code> agar AI membalas dalam JSON.
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Note JSON --}}
            <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                <div class="flex gap-2">
                    <flux:icon name="exclamation-triangle" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                    <p class="text-xs text-amber-800 dark:text-amber-300">
                        <strong>Catatan JSON:</strong> Parameter <code class="text-xs font-mono">format</code> yang diset ke <code class="text-xs font-mono">json</code> atau <code class="text-xs font-mono">json_object</code>
                        hanya meminta AI untuk membalas dalam format JSON — tidak menjamin hasilnya valid JSON jika model tidak mendukung structured output.
                        Dukungan per provider: Ollama ✓, OpenAI ✓, Gemini ✓, Grok ✓, Claude ✗ (diabaikan).
                    </p>
                </div>
            </div>

            {{-- Contoh Request — Text --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Contoh Request — Format Teks</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['ai'] }}/ai/prompt \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
  "system_prompt": "Kamu adalah asisten medis yang membantu tenaga kesehatan.",
  "user_prompt": "Jelaskan apa itu hipertensi secara singkat.",
  "format": "text"
}'</span></x-atoms.code-block>
            </div>

            {{-- Contoh Request — JSON --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Contoh Request — Format JSON</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['ai'] }}/ai/prompt \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
  "system_prompt": "Kamu adalah asisten medis. Selalu balas dalam format JSON.",
  "user_prompt": "Berikan 3 gejala umum hipertensi. Kembalikan dalam format {\"gejala\": [\"...\", \"...\", \"...\"]}",
  "format": "json_object"
}'</span></x-atoms.code-block>
            </div>

            {{-- Response Sukses --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response — <span class="text-emerald-500">200 OK</span>
                </h4>
                <x-atoms.code-block language="json">{
    "success": true,
    "data": {
        "response": "Hipertensi adalah kondisi medis ketika tekanan darah di arteri meningkat secara menetap..."
    }
}</x-atoms.code-block>
            </div>

            {{-- Response Fields --}}
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Field</x-atoms.table-heading>
                    <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                </x-slot:headings>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">success</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">boolean</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Status keberhasilan request.</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">data.response</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Teks balasan dari AI. Jika format JSON diminta, konten ini adalah string JSON yang perlu di-parse.</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>

            {{-- Response Error --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response — <span class="text-red-500">500 Internal Server Error</span>
                </h4>
                <x-atoms.code-block language="json">{
    "success": false,
    "message": "Gagal memproses prompt AI: [HTTP:401] Claude: invalid_api_key"
}</x-atoms.code-block>
            </div>

            {{-- Response Validation Error --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response — <span class="text-red-500">422 Unprocessable Entity</span> (validasi gagal)
                </h4>
                <x-atoms.code-block language="json">{
    "message": "The user prompt field is required.",
    "errors": {
        "user_prompt": ["The user prompt field is required."]
    }
}</x-atoms.code-block>
            </div>

            {{-- Info timeout --}}
            <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                <div class="flex gap-2">
                    <flux:icon name="information-circle" class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                    <div class="text-xs text-blue-800 dark:text-blue-200 space-y-1">
                        <p><strong>Timeout:</strong> Ollama memiliki timeout 120 detik (model lokal bisa lambat saat cold start). Provider cloud (Claude, OpenAI, Gemini, Grok) memiliki timeout 60 detik.</p>
                        <p><strong>Ukuran request:</strong> Maksimal 2 MB per request (dibatasi middleware <code class="text-xs font-mono">api.size:2048</code>).</p>
                        <p><strong>Log:</strong> Setiap request dicatat ke tabel <code class="text-xs font-mono">ai_logs</code> termasuk prompt, response, durasi, dan status.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
