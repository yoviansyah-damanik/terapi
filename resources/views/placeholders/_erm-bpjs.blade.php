<div class="flex flex-col lg:flex-row gap-6 mt-4 animate-pulse">
    <aside class="w-full lg:w-64 shrink-0">
        <div class="bg-white dark:bg-primary-dark-800 lg:rounded-2xl lg:border border-zinc-200 dark:border-primary-dark-700 lg:shadow-sm overflow-hidden">
            <div class="hidden lg:block px-4 pt-4 pb-3 border-b border-zinc-100 dark:border-primary-dark-700/50 mb-2">
                <div class="h-2.5 w-20 bg-zinc-200 dark:bg-primary-dark-700 rounded"></div>
            </div>
            <div class="hidden lg:flex flex-col px-2 pb-2 space-y-1">
                @for ($i = 0; $i < 4; $i++)
                    <div class="h-10 bg-zinc-100 dark:bg-primary-dark-700/40 rounded-xl"></div>
                @endfor
            </div>
            <div class="flex lg:hidden gap-1 p-1">
                <div class="h-9 w-20 bg-zinc-200 dark:bg-primary-dark-700 rounded-lg shrink-0"></div>
                <div class="h-9 w-20 bg-zinc-200 dark:bg-primary-dark-700 rounded-lg shrink-0"></div>
                <div class="h-9 w-20 bg-zinc-200 dark:bg-primary-dark-700 rounded-lg shrink-0"></div>
            </div>
        </div>
    </aside>
    <div class="flex-1 min-w-0 space-y-4">
        <div class="bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/50 flex items-center gap-3">
                <div class="h-4 w-8 bg-zinc-200 dark:bg-primary-dark-700 rounded"></div>
                <div class="h-4 w-28 bg-zinc-200 dark:bg-primary-dark-700 rounded"></div>
            </div>
            <div class="p-5 space-y-3">
                <div class="h-3 w-full bg-zinc-100 dark:bg-primary-dark-700/40 rounded"></div>
                <div class="h-3 w-4/5 bg-zinc-100 dark:bg-primary-dark-700/40 rounded"></div>
                <div class="h-3 w-3/5 bg-zinc-100 dark:bg-primary-dark-700/40 rounded"></div>
            </div>
        </div>
        <div class="bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm p-5 space-y-3">
            <div class="h-3 w-full bg-zinc-100 dark:bg-primary-dark-700/40 rounded"></div>
            <div class="h-3 w-5/6 bg-zinc-100 dark:bg-primary-dark-700/40 rounded"></div>
        </div>
    </div>
</div>
