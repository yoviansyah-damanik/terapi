<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <div>
        <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Document Reference</p>
        <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">Instruksi resep farmasi — 1 dokumen per resep
        </p>
    </div>
    @if ($telaahFarmasis->isNotEmpty() && $ssMedications->isNotEmpty())
        <x-atoms.button wire:click="sendSsDocumentReferences" wire:loading.attr="disabled" icon="paper-airplane"
            size="sm" variant="primary">
            <span wire:loading.remove wire:target="sendSsDocumentReferences">Kirim Terpilih</span>
            <span wire:loading wire:target="sendSsDocumentReferences">Mengirim...</span>
        </x-atoms.button>
    @endif
</div>

@if ($telaahFarmasis->isNotEmpty())
    @php
        // Pre-compute no_resep yang bisa dipilih: belum terkirim dan prasyarat terpenuhi
        $selectableDocRefIds = $telaahFarmasis
            ->filter(function ($tf) use ($ssDocumentReferences, $ssMedications, $ssQuestionnaireResponses, $medList) {
                if ($ssDocumentReferences->where('local_id', $tf->no_resep)->isNotEmpty()) {
                    return false;
                }

                $tglYmd = $tf->tgl_telaah ? \Carbon\Carbon::parse($tf->tgl_telaah)->format('Ymd') : null;
                $kodeBrngList = $tf->kode_brng_list ?? collect();

                $resepMedItems = $kodeBrngList->isNotEmpty()
                    ? $medList->filter(
                        fn($m) => $kodeBrngList->contains($m->kode_brng) &&
                            ($tglYmd ? str_contains($m->idStr, "-{$tglYmd}-") : true),
                    )
                    : ($tglYmd
                        ? $medList->filter(fn($m) => str_contains($m->idStr, "-{$tglYmd}-"))
                        : collect());

                $sentIds = $ssMedications->pluck('local_id');
                $medReqOk =
                    $resepMedItems->isNotEmpty() &&
                    $resepMedItems->filter(fn($m) => !$sentIds->contains($m->idStr))->isEmpty();
                $qrOk = $ssQuestionnaireResponses->contains(
                    fn($qr) => str_contains($qr->local_id, "-QR_{$tf->no_resep}-"),
                );

                return $medReqOk && $qrOk;
            })
            ->pluck('no_resep')
            ->values();
    @endphp
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-10 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked
                                ? $wire.set('ssSelectedDocumentReferences', {{ $selectableDocRefIds->toJson() }})
                                : $wire.set('ssSelectedDocumentReferences', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">No. Resep</th>
                    <th class="{{ $thClass }}">Tgl. Peresepan</th>
                    <th class="{{ $thClass }}">Prasyarat</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($telaahFarmasis as $tf)
                    @php
                        $syncedDocRef = $ssDocumentReferences->where('local_id', $tf->no_resep)->first();
                        $tglYmd = $tf->tgl_telaah ? \Carbon\Carbon::parse($tf->tgl_telaah)->format('Ymd') : null;
                        $kodeBrngList = $tf->kode_brng_list ?? collect();

                        // Filter medList per kode_brng resep + tanggal (aman untuk multi-resep sehari)
                        if ($kodeBrngList->isNotEmpty()) {
                            $resepMedItems = $medList->filter(
                                fn($m) => $kodeBrngList->contains($m->kode_brng) &&
                                    ($tglYmd ? str_contains($m->idStr, "-{$tglYmd}-") : true),
                            );
                        } else {
                            $resepMedItems = $tglYmd
                                ? $medList->filter(fn($m) => str_contains($m->idStr, "-{$tglYmd}-"))
                                : collect();
                        }

                        // Cek per kode obat: apakah MedicationRequest-nya sudah terkirim
                        $sentIds = $ssMedications->pluck('local_id');
                        $missingItems = $resepMedItems->filter(fn($m) => !$sentIds->contains($m->idStr));
                        $medReqOk = $resepMedItems->isNotEmpty() && $missingItems->isEmpty();

                        // Cek QuestionnaireResponse terkirim untuk no_resep ini
                        $sentQr = $ssQuestionnaireResponses->first(
                            fn($qr) => str_contains($qr->local_id, "-QR_{$tf->no_resep}-"),
                        );
                        $qrOk = $sentQr !== null;

                        $prereqOk = $medReqOk && $qrOk;
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedDocRef)
                                <flux:icon name="check-circle" variant="solid" class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif (!$prereqOk)
                                <flux:icon name="lock-closed" variant="solid"
                                    class="w-4 h-4 text-zinc-400 dark:text-primary-dark-500 mx-auto"
                                    title="Prasyarat belum terpenuhi" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedDocumentReferences"
                                    value="{{ $tf->no_resep }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdText }} font-mono">{{ $tf->no_resep }}</td>
                        <td class="{{ $tdMuted }} text-xs">
                            {{ $tf->tgl_telaah ?? '-' }}
                            @if ($tf->jam_telaah)
                                <br>{{ $tf->jam_telaah }}
                            @endif
                        </td>
                        <td class="{{ $tdMuted }}">
                            <div class="flex flex-col gap-1.5">
                                {{-- MedicationRequest per kode obat --}}
                                <div class="flex items-start gap-1">
                                    @if ($resepMedItems->isEmpty())
                                        <flux:icon name="question-mark-circle" variant="solid"
                                            class="w-3.5 h-3.5 text-zinc-400 shrink-0 mt-px" />
                                        <span class="text-[10px] text-zinc-400">MedRequest — data obat tidak
                                            ditemukan</span>
                                    @elseif ($medReqOk)
                                        <flux:icon name="check-circle" variant="solid"
                                            class="w-3.5 h-3.5 text-green-500 shrink-0 mt-px" />
                                        <span class="text-[10px] text-green-600 dark:text-green-400">
                                            MedRequest ({{ $resepMedItems->count() }}/{{ $resepMedItems->count() }}
                                            terkirim)
                                        </span>
                                    @else
                                        <flux:icon name="x-circle" variant="solid"
                                            class="w-3.5 h-3.5 text-red-400 shrink-0 mt-px" />
                                        <div class="flex flex-col gap-0.5">
                                            <span class="text-[10px] text-red-500 dark:text-red-400">
                                                MedRequest
                                                ({{ $resepMedItems->count() - $missingItems->count() }}/{{ $resepMedItems->count() }}
                                                terkirim)
                                            </span>
                                            @foreach ($missingItems as $missing)
                                                <span class="text-[10px] font-mono text-red-400 dark:text-red-500 pl-1">
                                                    ↳
                                                    {{ $missing->kode_brng }}{{ $missing->nama_brng ? " — {$missing->nama_brng}" : '' }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                {{-- QuestionnaireResponse --}}
                                <div class="flex items-center gap-1">
                                    @if ($qrOk)
                                        <flux:icon name="check-circle" variant="solid"
                                            class="w-3.5 h-3.5 text-green-500 shrink-0" />
                                        <span
                                            class="text-[10px] text-green-600 dark:text-green-400">QuestionnaireResponse</span>
                                    @else
                                        <flux:icon name="x-circle" variant="solid"
                                            class="w-3.5 h-3.5 text-red-400 shrink-0" />
                                        <span class="text-[10px] text-red-500 dark:text-red-400">QuestionnaireResponse
                                            belum dikirim</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="{{ $tdMuted }}">
                            @if ($syncedDocRef)
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                    <span class="text-[10px] font-mono">{{ $syncedDocRef->ihs_number }}</span>
                                    <span
                                        class="text-[10px]">{{ $syncedDocRef->synced_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            @elseif (!$prereqOk)
                                <span class="text-amber-500 dark:text-amber-400 text-xs">Prasyarat belum
                                    terpenuhi</span>
                            @else
                                <span class="text-zinc-400">Belum dikirim</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if ($syncedDocRef)
                                <button type="button" wire:click="openSsDetail('{{ $syncedDocRef->ihs_number }}')"
                                    class="p-1.5 rounded-lg text-zinc-400 hover:text-sky-600 hover:bg-sky-50 dark:text-primary-dark-500 dark:hover:text-sky-400 dark:hover:bg-sky-900/20 transition-colors"
                                    title="Lihat detail sinkronisasi">
                                    <flux:icon name="eye" class="w-4 h-4" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="flex flex-col items-center py-10">
        <flux:icon name="document-text" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data resep farmasi untuk kunjungan
            ini.</p>
        <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-1">Pastikan data telaah farmasi tersedia di SIMRS.
        </p>
    </div>
@endif
