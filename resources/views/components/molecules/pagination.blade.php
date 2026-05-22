@props([
    'page'       => 1,          // halaman aktif
    'totalPage'  => 1,          // total halaman
    'total'      => 0,          // total row
    'onPrev'     => null,       // wire action string
    'onNext'     => null,       // wire action string
    'onGoto'     => null,       // wire action string — dipanggil dgn arg nomor halaman
    'pageNumbers' => [],        // array nomor halaman (null = ellipsis)
])

<div class="flex flex-col items-center gap-3 px-5 py-3 sm:flex-row sm:justify-between w-full">

    {{-- Kiri: info total & halaman --}}
    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 shrink-0 select-none">
        Menampilkan halaman
        <span class="font-semibold text-zinc-600 dark:text-primary-dark-300">{{ $page }}</span>
        dari
        <span class="font-semibold text-zinc-600 dark:text-primary-dark-300">{{ number_format($totalPage) }}</span>
        &nbsp;&middot;&nbsp;
        <span class="font-semibold text-zinc-600 dark:text-primary-dark-300">{{ number_format($total) }}</span>
        entri
    </p>

    {{-- Kanan: tombol navigasi --}}
    <nav class="flex items-center gap-1" aria-label="Pagination">

        {{-- Prev --}}
        <button
            @if ($onPrev) wire:click="{{ $onPrev }}" @endif
            @if ($page <= 1) disabled @endif
            class="flex items-center justify-center w-8 h-8 rounded-lg text-zinc-400 dark:text-primary-dark-500
                   ring-1 ring-zinc-200 dark:ring-primary-dark-700 bg-white dark:bg-primary-dark-800
                   hover:bg-zinc-50 dark:hover:bg-primary-dark-700 hover:text-zinc-600 dark:hover:text-primary-dark-300
                   disabled:opacity-30 disabled:cursor-not-allowed transition-all duration-150"
            aria-label="Halaman sebelumnya">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
        </button>

        {{-- Page numbers --}}
        @foreach ($pageNumbers as $num)
            @if ($num === null)
                <span class="flex items-center justify-center w-8 h-8 text-xs text-zinc-300 dark:text-primary-dark-600 select-none">
                    &hellip;
                </span>
            @else
                <button
                    @if ($onGoto) wire:click="{{ $onGoto }}({{ $num }})" @endif
                    aria-label="Halaman {{ $num }}"
                    aria-current="{{ $page === $num ? 'page' : 'false' }}"
                    class="flex items-center justify-center w-8 h-8 rounded-lg text-xs font-medium transition-all duration-150
                        {{ $page === $num
                            ? 'bg-primary-600 dark:bg-primary-500 text-white shadow-sm shadow-primary-500/30 ring-0'
                            : 'text-zinc-500 dark:text-primary-dark-400 ring-1 ring-zinc-200 dark:ring-primary-dark-700 bg-white dark:bg-primary-dark-800 hover:bg-zinc-50 dark:hover:bg-primary-dark-700 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    {{ $num }}
                </button>
            @endif
        @endforeach

        {{-- Next --}}
        <button
            @if ($onNext) wire:click="{{ $onNext }}" @endif
            @if ($page >= $totalPage) disabled @endif
            class="flex items-center justify-center w-8 h-8 rounded-lg text-zinc-400 dark:text-primary-dark-500
                   ring-1 ring-zinc-200 dark:ring-primary-dark-700 bg-white dark:bg-primary-dark-800
                   hover:bg-zinc-50 dark:hover:bg-primary-dark-700 hover:text-zinc-600 dark:hover:text-primary-dark-300
                   disabled:opacity-30 disabled:cursor-not-allowed transition-all duration-150"
            aria-label="Halaman berikutnya">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
        </button>

    </nav>
</div>
