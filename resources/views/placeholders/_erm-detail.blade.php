<div>
    {{-- Breadcrumb / back skeleton --}}
    <div class="h-4 w-32 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse mb-4"></div>

    {{-- Info cards skeleton --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @for ($i = 0; $i < 4; $i++)
            <div class="bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-100 dark:border-primary-dark-700 p-4">
                <div class="h-3 w-20 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse mb-3"></div>
                <div class="h-5 w-32 bg-zinc-200 dark:bg-primary-dark-600 rounded animate-pulse mb-1.5"></div>
                <div class="h-3 w-24 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse"></div>
            </div>
        @endfor
    </div>

    {{-- Tab bar skeleton --}}
    <div class="bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-100 dark:border-primary-dark-700 overflow-hidden">
        <div class="flex border-b border-zinc-100 dark:border-primary-dark-700 px-4 gap-1 pt-2">
            @foreach ([80, 96, 88, 104] as $w)
                <div class="h-9 rounded-t-lg animate-pulse" style="width: {{ $w }}px; background: {{ $loop->first ? 'rgb(209 213 219)' : 'rgb(244 244 245)' }}"></div>
            @endforeach
        </div>

        {{-- Tab content skeleton --}}
        <div class="p-6 space-y-4">
            <div class="h-5 w-48 bg-zinc-200 dark:bg-primary-dark-600 rounded animate-pulse"></div>
            <div class="space-y-3">
                @for ($i = 0; $i < 6; $i++)
                    <div class="flex gap-4">
                        <div class="h-4 w-28 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse shrink-0"></div>
                        <div class="h-4 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse" style="width: {{ [160, 120, 200, 140, 180, 100][$i] }}px"></div>
                    </div>
                @endfor
            </div>

            <div class="pt-4 border-t border-zinc-100 dark:border-primary-dark-700 flex gap-2">
                <div class="h-9 w-32 bg-primary-200 dark:bg-primary-900/40 rounded-lg animate-pulse"></div>
                <div class="h-9 w-32 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
            </div>
        </div>
    </div>
</div>
