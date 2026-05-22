<div class="space-y-4">
    @if (count($usgResults) > 0)
        <div
            class="px-4 py-3 bg-white rounded-t-lg shadow dark:bg-primary-dark-800 border-b border-zinc-200 dark:border-primary-dark-700">
            <h4 class="flex items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                <flux:icon name="signal" class="w-4 h-4" /> Hasil Pemeriksaan USG
                <flux:badge color="zinc" size="sm">{{ $totalUsg }}</flux:badge>
            </h4>
        </div>
        @foreach ($usgResults as $usgKey => $usg)
            @foreach ($usg['data'] as $usgItem)
                <div
                    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                    <div
                        class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                            {{ $usg['label'] }}</h4>
                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                            {{ \Carbon\Carbon::parse($usgItem->tanggal)->format('d/m/Y') }} &middot;
                            Dokter: {{ $usg['dokters'][$usgItem->kd_dokter] ?? '-' }}@if (!empty($usgItem->kiriman_dari))
                                &middot; Kiriman dari: {{ $usgItem->kiriman_dari }}
                            @endif
                        </p>
                    </div>
                    <div class="p-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-5">
                            @if (!empty($usgItem->diagnosa_klinis))
                                <div class="md:col-span-2 pb-3 border-b border-zinc-100 dark:border-primary-dark-700/50">
                                    <span
                                        class="text-[10px] font-bold tracking-widest uppercase text-zinc-400 dark:text-primary-dark-500">Diagnosa
                                        Klinis</span>
                                    <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                        {{ $usgItem->diagnosa_klinis }}</p>
                                </div>
                            @endif
                            
                            @foreach ($usg['fields'] as $field => $label)
                                @if (!empty($usgItem->$field))
                                    <div class="space-y-1">
                                        <span
                                            class="text-[10px] font-bold tracking-widest uppercase text-zinc-400 dark:text-primary-dark-500">{{ $label }}</span>
                                        <p class="text-sm text-zinc-800 dark:text-primary-dark-200 leading-relaxed">
                                            {!! nl2br(e($usgItem->$field)) !!}</p>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
            @if ($usg['gambar']->count() > 0)
                <div
                    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                    <div
                        class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                            Gambar
                            {{ $usg['label'] }}</h4>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                            @foreach ($usg['gambar'] as $photo)
                                <a href="{{ $photo }}" target="_blank"
                                    class="block overflow-hidden rounded-xl border border-zinc-200/80 dark:border-primary-dark-700/60 hover:ring-2 hover:ring-primary-500">
                                    <img src="{{ $photo }}" alt="{{ $usg['label'] }}"
                                        class="object-cover w-full h-32" loading="lazy">
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @else
        <div
            class="flex flex-col items-center py-12 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
            <flux:icon name="signal" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data USG</p>
        </div>
    @endif
</div>
