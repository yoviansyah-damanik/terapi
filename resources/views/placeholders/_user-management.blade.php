<div class="animate-pulse overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200/60 dark:bg-primary-dark-800 dark:ring-primary-dark-700/60">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
        <div class="flex items-center gap-2">
            <div class="size-4 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
            <div class="h-4 w-32 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
        </div>
        <div class="h-8 w-36 rounded-lg bg-zinc-200 dark:bg-primary-dark-700"></div>
    </div>

    {{-- Filter bar --}}
    <div class="px-5 py-3.5 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/60 dark:bg-primary-dark-900/30">
        <div class="grid grid-cols-4 gap-3">
            <div class="col-span-2 h-8 rounded-lg bg-zinc-200 dark:bg-primary-dark-700/60"></div>
            <div class="h-8 rounded-lg bg-zinc-200 dark:bg-primary-dark-700/60"></div>
            <div class="h-8 rounded-lg bg-zinc-200 dark:bg-primary-dark-700/60"></div>
        </div>
    </div>

    {{-- Table header --}}
    <div class="grid grid-cols-6 gap-4 px-5 py-3 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/40 dark:bg-primary-dark-900/20">
        <div class="col-span-2 h-3 w-20 rounded bg-zinc-200 dark:bg-primary-dark-700/60"></div>
        <div class="h-3 w-16 rounded bg-zinc-200 dark:bg-primary-dark-700/60"></div>
        <div class="h-3 w-10 rounded bg-zinc-200 dark:bg-primary-dark-700/60"></div>
        <div class="h-3 w-10 rounded bg-zinc-200 dark:bg-primary-dark-700/60"></div>
        <div class="h-3 w-12 rounded bg-zinc-200 dark:bg-primary-dark-700/60 ml-auto"></div>
    </div>

    {{-- Rows --}}
    @foreach (range(1, 6) as $i)
        <div class="flex items-center gap-4 px-5 py-3.5 border-b border-zinc-50 dark:border-primary-dark-700/30 last:border-0">
            {{-- Avatar + nama + email --}}
            <div class="flex items-center gap-3 flex-[2]">
                <div class="size-9 rounded-full bg-zinc-200 dark:bg-primary-dark-700 shrink-0"></div>
                <div class="space-y-1.5">
                    <div class="h-3 w-28 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
                    <div class="h-2.5 w-36 rounded bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                </div>
            </div>
            {{-- Username --}}
            <div class="flex-1">
                <div class="h-3 w-20 rounded bg-zinc-100 dark:bg-primary-dark-700/40"></div>
            </div>
            {{-- Badge role --}}
            <div class="flex-1">
                <div class="h-5 w-14 rounded-full bg-zinc-100 dark:bg-primary-dark-700/40"></div>
            </div>
            {{-- Badge status --}}
            <div class="flex-1">
                <div class="h-5 w-12 rounded-full bg-zinc-100 dark:bg-primary-dark-700/40"></div>
            </div>
            {{-- Last login --}}
            <div class="flex-1">
                <div class="h-3 w-20 rounded bg-zinc-100 dark:bg-primary-dark-700/40"></div>
            </div>
            {{-- Actions --}}
            <div class="flex items-center gap-1">
                <div class="size-7 rounded-lg bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                <div class="size-7 rounded-lg bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                <div class="size-7 rounded-lg bg-zinc-100 dark:bg-primary-dark-700/40"></div>
            </div>
        </div>
    @endforeach
</div>
