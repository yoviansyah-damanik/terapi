<div>
    {{-- Section: Kirim Prompt AI --}}
    <x-ui.section-card id="ai-prompt" title="Kirim Prompt AI" x-show="activeSection === 'ai-prompt'"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
        style="display: none;">
        <x-slot:header>
            <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Kirim Prompt (Generate Text)</h3>
            <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">
                Mengirim prompt ke AI Provider aktif yang disetel pada konfigurasi (Ollama, Gemini, OpenAI, Claude, Grok).
            </p>
        </x-slot:header>

        <div class="space-y-6">
            <div class="p-4 border rounded-lg border-zinc-200 dark:border-primary-dark-700">
                <div class="flex items-center gap-3">
                    <span class="px-2 py-1 text-xs font-bold text-white bg-green-600 rounded">POST</span>
                    <code class="text-sm text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['ai'] }}/ai/prompt</code>
                </div>
            </div>

            <div>
                <h4 class="text-xs font-semibold text-zinc-900 uppercase dark:text-primary-dark-100 mb-3 tracking-wider">Parameter Request</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib</x-atoms.table-heading>
                        <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">user_prompt</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-purple-600 dark:text-purple-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-red-600 dark:text-red-400">Ya</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-300">Teks instruksi atau pertanyaan yang akan dikirim ke AI.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">system_prompt</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-purple-600 dark:text-purple-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell>Tidak</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-300">Instruksi konteks perilaku AI (Default: <code>Berikan respon yang singkat dan padat.</code>)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">format</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-purple-600 dark:text-purple-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell>Tidak</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-300">Menentukan format kembalian. Isi dengan <code>json_object</code> atau <code>text</code>. Bila diset JSON, pastikan request Anda juga menyertakan perintah agar AI membalas JSON. Default: <code>text</code></x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['ai'] }}/ai/prompt \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
  "system_prompt": "Kamu adalah asisten medis.",
  "user_prompt": "Jelaskan apa itu hipertensi",
  "format": "text"
}'</span></x-atoms.code-block>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Response</h4>
                <x-atoms.code-block language="json">{
    "success": true,
    "data": {
        "response": "Hipertensi adalah istilah medis untuk tekanan darah tinggi..."
    }
}</x-atoms.code-block>
            </div>
        </div>
    </x-ui.section-card>
</div>
