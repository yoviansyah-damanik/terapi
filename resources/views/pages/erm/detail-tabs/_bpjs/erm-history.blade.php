<div class="p-5">
    @php
        $ermLogsData = $ermLogs
            ->map(
                fn($log) => [
                    'id' => $log->id,
                    'status' => $log->status,
                    'status_label' => $log->status_label,
                    'status_color' => $log->status_badge_color,
                    'method' => $log->method ?? 'POST',
                    'endpoint' => $log->endpoint ?? '',
                    'response_status' => $log->response_status,
                    'response_time_ms' => $log->response_time ? (int) round($log->response_time * 1000) : null,
                    'no_sep' => $log->no_sep,
                    'error_message' => $log->error_message,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'sent_at' => $log->sent_at?->format('d/m/Y H:i:s'),
                    'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
                    'request_payload' => $log->request_payload,
                    'response_payload' => $log->response_payload,
                    'bundle' => $log->bundle,
                ],
            )
            ->values();
    @endphp

    <div x-data="{
        logs: @js($ermLogsData),
        selected: null,
        logTab: 'info',
        fmt(val) {
            if (val === null || val === undefined) return '—';
            try { return JSON.stringify(typeof val === 'string' ? JSON.parse(val) : val, null, 2); } catch (e) { return String(val); }
        },
        open(id) {
            this.selected = this.logs.find(l => l.id == id) ?? null;
            this.logTab = 'info';
            if (this.selected) {
                this.$nextTick(() => {
                    this.$dispatch('update-bundle', this.fmt(this.selected.bundle));
                    this.$dispatch('update-request', this.fmt(this.selected.request_payload));
                    this.$dispatch('update-response', this.fmt(this.selected.response_payload));
                });
                $flux.modal('erm-log-modal').show();
            }
        }
    }">
        <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">
                Riwayat Pengiriman eRM
            </p>
            <div>
                <x-atoms.button wire:click="generateJson" size="sm" icon="code-bracket" :disabled="$hasErmErrors">Generate
                    JSON</x-atoms.button>
                <x-atoms.button wire:click="sendErm" size="sm" icon="paper-airplane" variant="primary"
                    wire:loading.attr="disabled" :disabled="$hasErmErrors">
                    <span wire:loading.remove wire:target="sendErm">
                        {{ $hasSuccessErm ? 'Update eRM' : 'Kirim eRM Baru' }}
                    </span>
                    <span wire:loading wire:target="sendErm">Mengirim...</span>
                </x-atoms.button>
            </div>
        </div>

        @if ($ermLogs->isNotEmpty())
            <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading align="center" class="px-3 py-2.5 w-8">#</x-atoms.table-heading>
                        <x-atoms.table-heading class="px-3 py-2.5 whitespace-nowrap">Waktu</x-atoms.table-heading>
                        <x-atoms.table-heading class="px-3 py-2.5">Status</x-atoms.table-heading>
                        <x-atoms.table-heading align="center" class="px-3 py-2.5">HTTP</x-atoms.table-heading>
                        <x-atoms.table-heading class="px-3 py-2.5 hidden sm:table-cell">Endpoint</x-atoms.table-heading>
                        <x-atoms.table-heading align="right"
                            class="px-3 py-2.5 hidden md:table-cell whitespace-nowrap">Resp.
                            (ms)</x-atoms.table-heading>
                        <x-atoms.table-heading align="center" class="px-3 py-2.5">Aksi</x-atoms.table-heading>
                    </x-slot:headings>

                    @foreach ($ermLogs as $i => $log)
                        <x-molecules.table-row>
                            <x-atoms.table-cell align="center"
                                class="px-3 py-2.5 text-xs font-mono text-zinc-400 dark:text-primary-dark-600 w-8">{{ ($ermLogs->currentPage() - 1) * $ermLogs->perPage() + $i + 1 }}</x-atoms.table-cell>
                            <x-atoms.table-cell nowrap
                                class="px-3 py-2.5 text-xs text-zinc-500 dark:text-primary-dark-400">{{ $log->created_at?->format('d/m/Y H:i:s') }}</x-atoms.table-cell>
                            <x-atoms.table-cell class="px-3 py-2.5">
                                <x-atoms.status-badge :status="$log->status" />
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="center" class="px-3 py-2.5">
                                @if ($log->response_status)
                                    <x-atoms.status-badge :status="$log->response_status" />
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-primary-dark-700">—</span>
                                @endif
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="px-3 py-2.5 hidden sm:table-cell max-w-[180px]">
                                <span class="text-xs font-mono text-zinc-400 dark:text-primary-dark-500 truncate block"
                                    title="{{ $log->endpoint }}">
                                    {{ $log->endpoint ? Str::afterLast($log->endpoint, '/eclaim') : '—' }}
                                </span>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="right" class="px-3 py-2.5 hidden md:table-cell">
                                @if ($log->response_time)
                                    <span
                                        class="text-xs font-mono text-zinc-500 dark:text-primary-dark-400">{{ (int) round($log->response_time * 1000) }}</span>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-primary-dark-700">—</span>
                                @endif
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="center" action>
                                <x-atoms.button size="xs" variant="ghost" icon="eye"
                                    x-on:click="open('{{ $log->id }}')">Detail</x-atoms.button>
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @endforeach
                </x-organisms.table>
            </div>
            @if ($ermLogs->hasPages())
                <div class="mt-4 flex justify-end">
                    {{ $ermLogs->links() }}
                </div>
            @endif
        @else
            <x-ui.empty-state icon="paper-airplane" title="Belum ada riwayat pengiriman eRM." />
        @endif

        {{-- Modal Detail Log --}}
        <x-organisms.modal name="erm-log-modal" title="Detail Log Pengiriman eRM" maxWidth="3xl">
            <div x-show="selected !== null" class="space-y-4">
                {{-- Metadata: waktu + status badge --}}
                <div class="flex items-center justify-between">
                    <p class="text-xs font-mono text-zinc-400 dark:text-primary-dark-500"
                        x-text="selected?.created_at ?? ''"></p>
                    <span x-show="selected"
                        :class="{
                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': selected
                                ?.status_color === 'green',
                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': selected
                                ?.status_color === 'red',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': selected
                                ?.status_color === 'amber',
                            'bg-zinc-100 text-zinc-600 dark:bg-primary-dark-700 dark:text-primary-dark-400':
                                !['green', 'red', 'amber'].includes(selected?.status_color),
                        }"
                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                        x-text="selected?.status_label">
                    </span>
                </div>

                {{-- Tab Nav --}}
                <div class="flex gap-1 p-1 bg-zinc-100 dark:bg-primary-dark-900 rounded-xl">
                    @foreach ([['info', 'Informasi'], ['bundle', 'Bundle'], ['request', 'Request'], ['response', 'Response']] as [$tid, $tlabel])
                        <button x-on:click="logTab = '{{ $tid }}'"
                            :class="logTab === '{{ $tid }}'
                                ?
                                'bg-white dark:bg-primary-dark-800 shadow-sm text-zinc-900 dark:text-primary-dark-100 border border-zinc-200 dark:border-primary-dark-600' :
                                'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700'"
                            class="flex-1 rounded-lg px-3 py-1.5 text-xs font-medium transition-all">
                            {{ $tlabel }}
                        </button>
                    @endforeach
                </div>

                {{-- Tab: Info --}}
                <div x-show="logTab === 'info'" class="space-y-3">
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Method</dt>
                            <dd class="font-mono font-semibold text-zinc-800 dark:text-primary-dark-200"
                                x-text="selected?.method ?? '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">HTTP Status
                            </dt>
                            <dd class="font-mono font-semibold"
                                :class="selected?.response_status == 200 ?
                                    'text-green-600 dark:text-green-400' :
                                    'text-red-500 dark:text-red-400'"
                                x-text="selected?.response_status ?? '—'"></dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Endpoint</dt>
                            <dd class="font-mono text-xs text-zinc-700 dark:text-primary-dark-300 break-all"
                                x-text="selected?.endpoint ?? '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">No. SEP</dt>
                            <dd class="font-mono text-zinc-800 dark:text-primary-dark-200"
                                x-text="selected?.no_sep ?? '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Response Time
                            </dt>
                            <dd class="font-mono text-zinc-800 dark:text-primary-dark-200"
                                x-text="selected?.response_time_ms ? selected.response_time_ms + ' ms' : '—'">
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Dikirim Pada
                            </dt>
                            <dd class="text-zinc-800 dark:text-primary-dark-200" x-text="selected?.sent_at ?? '—'">
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">IP Address
                            </dt>
                            <dd class="font-mono text-zinc-800 dark:text-primary-dark-200"
                                x-text="selected?.ip_address ?? '—'"></dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">User Agent
                            </dt>
                            <dd class="text-xs text-zinc-500 dark:text-primary-dark-400 break-all"
                                x-text="selected?.user_agent ?? '—'"></dd>
                        </div>
                    </dl>
                    <template x-if="selected?.error_message">
                        <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm">
                            <p class="text-xs font-semibold mb-1">Error Message</p>
                            <p x-text="selected.error_message" class="font-mono text-xs break-all">
                            </p>
                        </div>
                    </template>
                </div>

                {{-- Tab: Bundle --}}
                <div x-show="logTab === 'bundle'" wire:ignore>
                    <x-atoms.code-block language="json" maxHeight="max-h-[45vh]"
                        @update-bundle.window="originalText = $event.detail; applyHighlight()">
                    </x-atoms.code-block>
                </div>

                {{-- Tab: Request --}}
                <div x-show="logTab === 'request'" wire:ignore>
                    <x-atoms.code-block language="json" maxHeight="max-h-[45vh]"
                        @update-request.window="originalText = $event.detail; applyHighlight()">
                    </x-atoms.code-block>
                </div>

                {{-- Tab: Response --}}
                <div x-show="logTab === 'response'" wire:ignore>
                    <x-atoms.code-block language="json" maxHeight="max-h-[45vh]"
                        @update-response.window="originalText = $event.detail; applyHighlight()">
                    </x-atoms.code-block>
                </div>
            </div>
        </x-organisms.modal>
    </div>
</div>
