 <div class="space-y-6">
     @if ($pemeriksaans->count() > 0)
         <div
             class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
             <div class="px-4 py-3 border-b bg-sky-50 dark:bg-sky-900/20 border-sky-200 dark:border-sky-700">
                 <h4 class="flex items-center gap-2 text-sm font-semibold text-sky-900 dark:text-sky-100">
                     <flux:icon name="clipboard-document-check" class="w-4 h-4" />
                     Pemeriksaan {{ $reg->status_lanjut === 'Ralan' ? 'Rawat Jalan' : 'Rawat Inap' }}
                 </h4>
             </div>
             <div class="overflow-x-auto">
                 <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                     <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                         <tr>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Tanggal & Jam</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Vital Signs</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Antropometri</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 GCS & Kesadaran</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Keluhan</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Pemeriksaan</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 RTL</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Petugas</th>
                         </tr>
                     </thead>
                     <tbody
                         class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                         @foreach ($pemeriksaans as $periksa)
                             <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                 <td class="px-4 py-3 whitespace-nowrap">
                                     <div class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                         {{ $periksa->tgl_perawatan?->format('d/m/Y') }}</div>
                                     <div class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                         {{ $periksa->jam_rawat }}</div>
                                 </td>
                                 <td class="px-4 py-3">
                                     <div class="space-y-1 text-xs">
                                         @if ($periksa->suhu_tubuh)
                                             <div class="text-zinc-700 dark:text-primary-dark-300">Suhu:
                                                 <span class="font-medium">{{ $periksa->suhu_tubuh }}°C</span>
                                             </div>
                                         @endif
                                         @if ($periksa->tensi)
                                             <div class="text-zinc-700 dark:text-primary-dark-300">
                                                 Tensi: <span class="font-medium">{{ $periksa->tensi }}
                                                     mmHg</span></div>
                                         @endif
                                         @if ($periksa->nadi)
                                             <div class="text-zinc-700 dark:text-primary-dark-300">Nadi:
                                                 <span class="font-medium">{{ $periksa->nadi }}
                                                     x/mnt</span>
                                             </div>
                                         @endif
                                         @if ($periksa->respirasi)
                                             <div class="text-zinc-700 dark:text-primary-dark-300">
                                                 Respirasi:
                                                 <span class="font-medium">{{ $periksa->respirasi }}
                                                     x/mnt</span>
                                             </div>
                                         @endif
                                         @if ($periksa->spo2)
                                             <div class="text-zinc-700 dark:text-primary-dark-300">SpO2:
                                                 <span class="font-medium">{{ $periksa->spo2 }}%</span>
                                             </div>
                                         @endif
                                     </div>
                                 </td>
                                 <td class="px-4 py-3">
                                     <div class="space-y-1 text-xs">
                                         @if ($periksa->tinggi)
                                             <div class="text-zinc-700 dark:text-primary-dark-300">TB:
                                                 <span class="font-medium">{{ $periksa->tinggi }}
                                                     cm</span>
                                             </div>
                                         @endif
                                         @if ($periksa->berat)
                                             <div class="text-zinc-700 dark:text-primary-dark-300">BB:
                                                 <span class="font-medium">{{ $periksa->berat }}
                                                     kg</span>
                                             </div>
                                         @endif
                                         @if ($periksa->lingkar_perut)
                                             <div class="text-zinc-700 dark:text-primary-dark-300">LP:
                                                 <span class="font-medium">{{ $periksa->lingkar_perut }}
                                                     cm</span>
                                             </div>
                                         @endif
                                     </div>
                                 </td>
                                 <td class="px-4 py-3">
                                     <div class="space-y-1 text-xs">
                                         @if ($periksa->gcs)
                                             <div class="text-zinc-700 dark:text-primary-dark-300">GCS:
                                                 <span class="font-medium">{{ $periksa->gcs }}</span>
                                             </div>
                                         @endif
                                         @if ($periksa->kesadaran)
                                             <div class="text-zinc-700 dark:text-primary-dark-300">
                                                 Kesadaran:
                                                 <span class="font-medium">{{ $periksa->kesadaran }}</span>
                                             </div>
                                         @endif
                                     </div>
                                 </td>
                                 <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                     {{ Str::limit($periksa->keluhan, 100) ?: '-' }}</td>
                                 <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                     {{ Str::limit($periksa->pemeriksaan, 100) ?: '-' }}</td>
                                 <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                     {{ Str::limit($periksa->rtl, 100) ?: '-' }}</td>
                                 <td
                                     class="px-4 py-3 text-sm whitespace-nowrap text-zinc-700 dark:text-primary-dark-300">
                                     {{ $periksa->petugas?->nama ?? '-' }}</td>
                             </tr>
                         @endforeach
                     </tbody>
                 </table>
             </div>
         </div>
     @endif

     @if ($catatanGizis->count() > 0)
         <div
             class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
             <div class="px-4 py-3 border-b bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700">
                 <h4 class="flex items-center gap-2 text-sm font-semibold text-green-900 dark:text-green-100">
                     <flux:icon name="heart" class="w-4 h-4" /> Catatan Gizi (ADIME)
                 </h4>
             </div>
             <div class="overflow-x-auto">
                 <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                     <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                         <tr>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Tanggal</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Asesmen</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Diagnosis</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Intervensi</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Monitoring</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Evaluasi</th>
                             <th
                                 class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                 Petugas</th>
                         </tr>
                     </thead>
                     <tbody
                         class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                         @foreach ($catatanGizis as $gizi)
                             <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                 <td
                                     class="px-4 py-3 whitespace-nowrap text-sm text-zinc-900 dark:text-primary-dark-100">
                                     {{ $gizi->tanggal?->format('d/m/Y') }}</td>
                                 <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                     {{ Str::limit($gizi->asesmen, 100) ?: '-' }}</td>
                                 <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                     {{ Str::limit($gizi->diagnosis, 100) ?: '-' }}</td>
                                 <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                     {{ Str::limit($gizi->intervensi, 100) ?: '-' }}</td>
                                 <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                     {{ Str::limit($gizi->monitoring, 100) ?: '-' }}</td>
                                 <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                     {{ Str::limit($gizi->evaluasi, 100) ?: '-' }}</td>
                                 <td
                                     class="px-4 py-3 text-sm whitespace-nowrap text-zinc-700 dark:text-primary-dark-300">
                                     {{ $gizi->petugas?->nama ?? '-' }}</td>
                             </tr>
                         @endforeach
                     </tbody>
                 </table>
             </div>
         </div>
     @endif

     @if ($pemeriksaans->count() === 0 && $catatanGizis->count() === 0)
         <div
             class="flex flex-col items-center py-12 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
             <flux:icon name="clipboard-document-check" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
             <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data pemeriksaan
             </p>
         </div>
     @endif
 </div>
