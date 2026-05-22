<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <div>
        <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Questionnaire
            Response
        </p>
        <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">Telaah Farmasi (Q0007)
            —
            data dari SIMRS telaah_farmasi</p>
    </div>
    @if ($telaahFarmasis->isNotEmpty())
        <x-atoms.button wire:click="sendSsQuestionnaireResponses" wire:loading.attr="disabled" icon="paper-airplane"
            size="sm" variant="primary">
            <span wire:loading.remove wire:target="sendSsQuestionnaireResponses">Kirim
                Terpilih</span>
            <span wire:loading wire:target="sendSsQuestionnaireResponses">Mengirim...</span>
        </x-atoms.button>
    @endif
</div>

@if ($telaahFarmasis->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked
                                                ? $wire.set('ssSelectedQuestionnaireResponses', {{ $telaahFarmasis->map(fn($tf) => $this->reg->no_rawat . '-QR_' . $tf->no_resep . '-' . \Carbon\Carbon::parse($tf->tgl_telaah)->format('Ymd') . '-' . str_replace(':', '', $tf->jam_telaah ?? '000000'))->values()->toJson() }})
                                                : $wire.set('ssSelectedQuestionnaireResponses', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">No. Resep</th>
                    <th class="{{ $thClass }}">Tgl. Telaah</th>
                    <th class="{{ $thClass }}">Telaah Resep</th>
                    <th class="{{ $thClass }}">Telaah Obat</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($telaahFarmasis as $tf)
                    @php
                        $idStr = $this->reg->no_rawat . '-QR_' . $tf->no_resep . '-' . \Carbon\Carbon::parse($tf->tgl_telaah)->format('Ymd') . '-' . str_replace(':', '', $tf->jam_telaah ?? '000000');
                        $syncedQr = $ssQuestionnaireResponses->where('local_id', $idStr)->first();
                        $resepItems = [
                            'Identifikasi Pasien' => $tf->resep_identifikasi_pasien,
                            'Tepat Obat' => $tf->resep_tepat_obat,
                            'Tepat Dosis' => $tf->resep_tepat_dosis,
                            'Cara Pemberian' => $tf->resep_tepat_cara_pemberian,
                            'Waktu Pemberian' => $tf->resep_tepat_waktu_pemberian,
                            'Duplikasi' => $tf->resep_ada_tidak_duplikasi_obat,
                            'Interaksi' => $tf->resep_interaksi_obat,
                            'Kontra Indikasi' => $tf->resep_kontra_indikasi_obat,
                        ];
                        $obatItems = [
                            'Tepat Pasien' => $tf->obat_tepat_pasien,
                            'Tepat Obat' => $tf->obat_tepat_obat,
                            'Tepat Dosis' => $tf->obat_tepat_dosis,
                            'Cara Pemberian' => $tf->obat_tepat_cara_pemberian,
                            'Waktu Pemberian' => $tf->obat_tepat_waktu_pemberian,
                        ];
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedQr)
                                <flux:icon name="check-circle" variant="solid" class="w-5 h-5 text-green-500 mx-auto" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedQuestionnaireResponses"
                                    value="{{ $idStr }}"
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
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($resepItems as $label => $val)
                                    <span
                                        class="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded
                                                        {{ $val ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' }}">
                                        <flux:icon name="{{ $val ? 'check' : 'x-mark' }}" class="w-2.5 h-2.5" />
                                        {{ $label }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($obatItems as $label => $val)
                                    <span
                                        class="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded
                                                        {{ $val ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' }}">
                                        <flux:icon name="{{ $val ? 'check' : 'x-mark' }}" class="w-2.5 h-2.5" />
                                        {{ $label }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="{{ $tdMuted }}">
                            @if ($syncedQr)
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                    <span class="text-[10px] font-mono">{{ $syncedQr->ihs_number }}</span>
                                    <span class="text-[10px]">{{ $syncedQr->synced_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            @else
                                <span class="text-zinc-400">Belum dikirim</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if ($syncedQr)
                                <button type="button" wire:click="openSsDetail('{{ $syncedQr->ihs_number }}')"
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
        <flux:icon name="document-magnifying-glass" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data Telaah
            Farmasi
            untuk kunjungan ini.</p>
        <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-1">Pastikan tabel <span
                class="font-mono">telaah_farmasi</span> di SIMRS terhubung dengan data kunjungan.
        </p>
    </div>
@endif
