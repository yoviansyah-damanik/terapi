<div class="flex gap-6 animate-pulse">

    {{-- Kolom kiri: daftar pengguna --}}
    <div class="w-72 shrink-0 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200/60 dark:bg-primary-dark-800 dark:ring-primary-dark-700/60">
        {{-- Header --}}
        <div class="flex items-center gap-2 px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
            <div class="size-4 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
            <div class="h-4 w-20 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
        </div>
        {{-- User rows --}}
        @foreach (range(1, 7) as $i)
            <div class="flex items-center gap-3 px-4 py-3 border-b border-zinc-50 dark:border-primary-dark-700/30 last:border-0">
                <div class="size-8 rounded-full bg-zinc-200 dark:bg-primary-dark-700 shrink-0"></div>
                <div class="flex-1 space-y-1.5 min-w-0">
                    <div class="h-3 w-{{ $i % 2 === 0 ? '28' : '24' }} rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
                    <div class="h-2.5 w-16 rounded bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Kolom kanan: empty state skeleton --}}
    <div class="flex-1 min-w-0 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200/60 dark:bg-primary-dark-800 dark:ring-primary-dark-700/60">
        {{-- Header --}}
        <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
            <div class="flex items-center gap-2">
                <div class="size-4 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
                <div class="h-4 w-40 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
            </div>
            <div class="flex gap-2">
                <div class="h-8 w-24 rounded-lg bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                <div class="h-8 w-24 rounded-lg bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                <div class="h-8 w-20 rounded-lg bg-zinc-200 dark:bg-primary-dark-700"></div>
            </div>
        </div>

        {{-- Permission groups --}}
        <div class="p-4 space-y-3">
            @foreach (range(1, 4) as $g)
                <div class="rounded-lg overflow-hidden ring-1 ring-zinc-200 dark:ring-primary-dark-700/60">
                    {{-- Group header --}}
                    <div class="flex items-center justify-between px-3 py-2 bg-zinc-50 dark:bg-primary-dark-900/30 border-b border-zinc-100 dark:border-primary-dark-700/40">
                        <div class="h-3 w-28 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
                        <div class="h-3 w-16 rounded bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                    </div>
                    {{-- Checkbox grid --}}
                    <div class="p-3 grid grid-cols-4 gap-2">
                        @foreach (range(1, $g === 1 ? 8 : ($g === 2 ? 6 : ($g === 3 ? 4 : 5))) as $c)
                            <div class="flex items-center gap-2">
                                <div class="size-3.5 rounded bg-zinc-100 dark:bg-primary-dark-700/40 shrink-0"></div>
                                <div class="h-2.5 rounded bg-zinc-100 dark:bg-primary-dark-700/40"
                                     style="width: {{ 40 + ($c * 7 % 30) }}px"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
