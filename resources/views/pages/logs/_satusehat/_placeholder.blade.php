<div>
{{-- Page Header Skeleton --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <div class="h-7 w-48 bg-zinc-200 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
        <div class="h-4 w-80 bg-zinc-100 dark:bg-primary-dark-800 rounded mt-2 animate-pulse"></div>
    </div>
    <div class="h-9 w-24 bg-zinc-100 dark:bg-primary-dark-800 rounded-lg animate-pulse"></div>
</div>

{{-- Tab Navigation Skeleton --}}
<div
    class="mb-5 overflow-hidden bg-white border rounded-2xl dark:bg-primary-dark-800/60 border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
    <div class="p-3">
        <div class="flex gap-1 p-1 rounded-xl bg-zinc-100 dark:bg-primary-dark-900/60">
            @foreach (range(1, 4) as $i)
                <div
                    class="flex flex-1 items-center justify-center gap-2 px-4 py-2 rounded-lg {{ $i === 1 ? 'bg-white dark:bg-primary-dark-700 shadow-sm' : '' }}">
                    <div class="h-4 w-4 rounded bg-zinc-200 dark:bg-primary-dark-600 animate-pulse"></div>
                    <div class="h-4 w-20 rounded bg-zinc-200 dark:bg-primary-dark-600 animate-pulse"></div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Stat Cards Skeleton --}}
<div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-5">
    @foreach (range(1, 5) as $i)
        <div
            class="p-4 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm">
            <div class="h-3 w-24 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse"></div>
            <div class="h-8 w-16 bg-zinc-200 dark:bg-primary-dark-600 rounded-lg mt-2 animate-pulse"></div>
        </div>
    @endforeach
</div>

{{-- Table Skeleton --}}
<div
    class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm overflow-hidden">
    {{-- Table Header --}}
    <div class="flex items-center gap-3 px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-900/40">
        <div class="h-4 w-32 bg-zinc-200 dark:bg-primary-dark-600 rounded animate-pulse"></div>
        <div class="h-3 w-48 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse"></div>
    </div>
    {{-- Filter Bar Skeleton --}}
    <div class="flex items-center gap-3 p-4 border-b border-zinc-100 dark:border-primary-dark-700">
        <div class="h-9 flex-1 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
        <div class="h-9 w-44 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
        <div class="h-9 w-44 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
        <div class="h-9 w-44 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
    </div>
    {{-- Table Rows Skeleton --}}
    @foreach (range(1, 8) as $i)
        <div
            class="flex items-center gap-4 px-4 py-3 border-b border-zinc-50 dark:border-primary-dark-700/50 last:border-0">
            <div class="h-4 w-24 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse"></div>
            <div class="h-5 w-20 bg-zinc-200 dark:bg-primary-dark-600 rounded-full animate-pulse"></div>
            <div class="h-3 w-72 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse flex-1"></div>
            <div class="h-4 w-16 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse"></div>
            <div class="h-5 w-12 bg-zinc-200 dark:bg-primary-dark-600 rounded animate-pulse"></div>
            <div class="h-7 w-7 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse ml-auto"></div>
        </div>
    @endforeach
</div>
</div>
