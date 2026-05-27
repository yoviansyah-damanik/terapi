<div>
    {{-- Stat Cards Skeleton --}}
    <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-4">
        @foreach (range(1, 4) as $i)
            <div
                class="p-4 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm">
                <div class="h-3 w-24 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse"></div>
                <div class="h-8 w-16 bg-zinc-200 dark:bg-primary-dark-600 rounded-lg mt-2 animate-pulse"></div>
            </div>
        @endforeach
    </div>

    {{-- Filter Bar Skeleton --}}
    <div
        class="mb-4 flex items-center gap-3 bg-white dark:bg-primary-dark-800 p-4 rounded-2xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm">
        <div class="h-9 flex-1 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
        <div class="h-9 w-44 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
        <div class="h-9 w-44 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
        <div class="h-9 w-40 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse ml-auto"></div>
    </div>

    {{-- Table Skeleton --}}
    <div
        class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm overflow-hidden">
        <div
            class="flex items-center gap-3 px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-900/40">
            <div class="h-4 w-40 bg-zinc-200 dark:bg-primary-dark-600 rounded animate-pulse"></div>
            <div class="h-3 w-56 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse"></div>
        </div>
        @foreach (range(1, 8) as $i)
            <div
                class="flex items-center gap-4 px-4 py-3 border-b border-zinc-50 dark:border-primary-dark-700/50 last:border-0">
                <div class="h-4 w-24 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse"></div>
                <div class="h-5 w-20 bg-zinc-200 dark:bg-primary-dark-600 rounded-full animate-pulse"></div>
                <div class="h-3 flex-1 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse"></div>
                <div class="h-4 w-16 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse"></div>
                <div class="h-5 w-14 bg-zinc-200 dark:bg-primary-dark-600 rounded animate-pulse"></div>
                <div class="h-7 w-7 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse ml-auto"></div>
            </div>
        @endforeach
    </div>
</div>
