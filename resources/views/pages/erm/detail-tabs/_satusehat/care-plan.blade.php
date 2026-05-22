 <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
     <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Care Plan (Instruksi
         Medik)
         <flux:badge color="{{ $ssCarePlans->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
             {{ $ssCarePlans->count() }}</flux:badge>
     </p>
     <x-atoms.button wire:click="sendSsCarePlans" wire:loading.attr="disabled" icon="paper-airplane" size="sm"
         variant="primary">
         <span wire:loading.remove wire:target="sendSsCarePlans">Kirim Care Plan</span>
         <span wire:loading wire:target="sendSsCarePlans">Mengirim...</span>
     </x-atoms.button>
 </div>

 @if ($instruksiList->isNotEmpty())
     <div class="overflow-x-auto">
         <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
             <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                 <tr>
                     <th class="{{ $thClass }} w-16 text-center">
                         <input type="checkbox"
                             x-on:change="$el.checked ? $wire.set('ssSelectedCarePlans', {{ $instruksiList->pluck('idStr')->toJson() }}) : $wire.set('ssSelectedCarePlans', [])"
                             class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                     </th>
                     <th class="{{ $thClass }}">Waktu</th>
                     <th class="{{ $thClass }}">Instruksi Medik / Keperawatan</th>
                     <th class="{{ $thClass }}">Status Sinkronisasi</th>
                     <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                 </tr>
             </thead>
             <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                 @foreach ($instruksiList as $ins)
                     @php
                         $syncedData = $ssCarePlans->where('local_id', $ins->idStr)->first();
                     @endphp
                     <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                         <td class="px-4 py-2 text-center">
                             @if ($syncedData)
                                 <flux:icon name="check-circle" variant="solid"
                                     class="w-5 h-5 text-green-500 mx-auto" />
                             @else
                                 <input type="checkbox" wire:model="ssSelectedCarePlans" value="{{ $ins->idStr }}"
                                     class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                             @endif
                         </td>
                         <td class="{{ $tdMuted }} text-xs">
                             {{ $ins->tgl_perawatan instanceof Carbon ? $ins->tgl_perawatan->format('d/m/Y') : $ins->tgl_perawatan }}
                             <br> {{ $ins->jam_rawat }}
                         </td>
                         <td class="{{ $tdText }}">
                             <div class="max-w-md line-clamp-3 hover:line-clamp-none transition-all">
                                 {{ $ins->instruksi }}
                             </div>
                         </td>
                         <td class="{{ $tdMuted }}">
                             @if ($syncedData)
                                 <div class="flex flex-col gap-0.5">
                                     <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                     <span class="text-[10px] font-mono">{{ $syncedData->ihs_number }}</span>
                                     <span
                                         class="text-[10px]">{{ $syncedData->synced_at?->format('d/m/Y H:i') }}</span>
                                 </div>
                             @else
                                 <span class="text-zinc-400">Belum didaftarkan</span>
                             @endif
                         </td>
                         <td class="px-4 py-2 text-center">
                             @if ($syncedData)
                                 <button type="button" wire:click="openSsDetail('{{ $syncedData->ihs_number }}')"
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
     <div
         class="flex flex-col items-center py-10 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
         <flux:icon name="clipboard" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
         <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada instruksi medik
             terisi
             untuk kunjungan ini.</p>
     </div>
 @endif
