<div>
    {{-- Page header skeleton --}}
    <div class="mb-6">
        <div class="h-7 w-48 bg-zinc-200 dark:bg-primary-dark-700 rounded animate-pulse mb-2"></div>
        <div class="h-4 w-80 bg-zinc-100 dark:bg-primary-dark-800 rounded animate-pulse"></div>
    </div>

    {{-- Filter bar skeleton --}}
    <div class="p-4 mb-6 bg-white dark:bg-primary-dark-800 rounded-lg shadow">
        <div class="flex flex-wrap items-center gap-3">
            <div class="h-9 w-36 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
            <div class="h-9 w-36 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
            <div class="h-9 w-36 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
            <div class="h-9 w-36 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
            <div class="h-9 w-24 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
        </div>
    </div>

    {{-- Table skeleton --}}
    <div class="bg-white dark:bg-primary-dark-800 rounded-lg shadow overflow-hidden">
        <div class="border-b border-zinc-100 dark:border-primary-dark-700 px-4 py-3 flex gap-4">
            @foreach ([80, 120, 72, 72, 96, 72, 80, 72] as $w)
                <div class="h-4 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse" style="width: {{ $w }}px"></div>
            @endforeach
        </div>
        @for ($i = 0; $i < 8; $i++)
            <div class="border-b border-zinc-50 dark:border-primary-dark-700/50 px-4 py-3.5 flex gap-4 items-center {{ $i % 2 === 0 ? '' : 'bg-zinc-50/50 dark:bg-primary-dark-900/20' }}">
                <div class="h-4 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse w-20"></div>
                <div class="h-4 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse w-32"></div>
                <div class="h-4 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse w-24"></div>
                <div class="h-4 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse w-20"></div>
                <div class="h-4 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse w-28"></div>
                <div class="h-5 w-16 bg-zinc-100 dark:bg-primary-dark-700 rounded-full animate-pulse"></div>
                <div class="h-5 w-16 bg-zinc-100 dark:bg-primary-dark-700 rounded-full animate-pulse"></div>
                <div class="flex gap-1.5 ml-auto">
                    <div class="h-7 w-7 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
                    <div class="h-7 w-7 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
                </div>
            </div>
        @endfor
        <div class="px-4 py-3 flex items-center justify-between">
            <div class="h-4 w-40 bg-zinc-100 dark:bg-primary-dark-700 rounded animate-pulse"></div>
            <div class="flex gap-1">
                @for ($i = 0; $i < 5; $i++)
                    <div class="h-8 w-8 bg-zinc-100 dark:bg-primary-dark-700 rounded-lg animate-pulse"></div>
                @endfor
            </div>
        </div>
    </div>
</div>
