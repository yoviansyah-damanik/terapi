<div class="mt-4 space-y-4 animate-pulse">
    <div class="bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/50 flex items-center gap-3">
            <div class="h-5 w-5 bg-zinc-200 dark:bg-primary-dark-700 rounded-full shrink-0"></div>
            <div class="h-4 w-40 bg-zinc-200 dark:bg-primary-dark-700 rounded"></div>
        </div>
        <div class="p-5 space-y-3">
            <div class="h-3 w-full bg-zinc-100 dark:bg-primary-dark-700/40 rounded"></div>
            <div class="h-3 w-4/5 bg-zinc-100 dark:bg-primary-dark-700/40 rounded"></div>
            <div class="h-3 w-3/5 bg-zinc-100 dark:bg-primary-dark-700/40 rounded"></div>
        </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        @foreach ([['w-12', 'w-16'], ['w-16', 'w-12'], ['w-14', 'w-10'], ['w-20', 'w-14']] as [$w1, $w2])
            <div class="bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm p-4 space-y-2">
                <div class="h-6 w-6 bg-zinc-200 dark:bg-primary-dark-700 rounded-lg"></div>
                <div class="h-3 {{ $w1 }} bg-zinc-200 dark:bg-primary-dark-700 rounded mt-2"></div>
                <div class="h-2.5 {{ $w2 }} bg-zinc-100 dark:bg-primary-dark-700/40 rounded"></div>
            </div>
        @endforeach
    </div>
    <div class="bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm p-5 space-y-3">
        <div class="h-3 w-full bg-zinc-100 dark:bg-primary-dark-700/40 rounded"></div>
        <div class="h-3 w-5/6 bg-zinc-100 dark:bg-primary-dark-700/40 rounded"></div>
    </div>
</div>
