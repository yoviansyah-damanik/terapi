@if ($paginator->hasPages())
<nav
    class="flex flex-col items-center gap-3 px-5 py-3 sm:flex-row sm:justify-between w-full"
    aria-label="Pagination"
    role="navigation">

    {{-- Kiri: Info total & halaman --}}
    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 shrink-0 select-none">
        Menampilkan halaman
        <span class="font-semibold text-zinc-600 dark:text-primary-dark-300">{{ $paginator->currentPage() }}</span>
        dari
        <span class="font-semibold text-zinc-600 dark:text-primary-dark-300">{{ number_format($paginator->lastPage()) }}</span>
        &nbsp;&middot;&nbsp;
        <span class="font-semibold text-zinc-600 dark:text-primary-dark-300">{{ number_format($paginator->total()) }}</span>
        entri
    </p>

    {{-- Kanan: Tombol navigasi --}}
    <div class="flex items-center gap-1">

        {{-- Prev --}}
        @if ($paginator->onFirstPage())
            <span class="flex items-center justify-center w-8 h-8 rounded-lg text-zinc-300 dark:text-primary-dark-700
                         ring-1 ring-zinc-200 dark:ring-primary-dark-700 bg-white dark:bg-primary-dark-800
                         cursor-not-allowed select-none"
                  aria-disabled="true" aria-label="Halaman sebelumnya">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </span>
        @else
            <button wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                    class="flex items-center justify-center w-8 h-8 rounded-lg text-zinc-400 dark:text-primary-dark-500
                           ring-1 ring-zinc-200 dark:ring-primary-dark-700 bg-white dark:bg-primary-dark-800
                           hover:bg-zinc-50 dark:hover:bg-primary-dark-700 hover:text-zinc-600 dark:hover:text-primary-dark-300
                           transition-all duration-150"
                    aria-label="Halaman sebelumnya">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </button>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)
            {{-- Ellipsis --}}
            @if (is_string($element))
                <span class="flex items-center justify-center w-8 h-8 text-xs text-zinc-300 dark:text-primary-dark-600 select-none">
                    &hellip;
                </span>
            @endif

            {{-- Array of page links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page"
                              class="flex items-center justify-center w-8 h-8 rounded-lg text-xs font-semibold
                                     bg-primary-600 dark:bg-primary-500 text-white
                                     shadow-sm shadow-primary-500/30 select-none">
                            {{ $page }}
                        </span>
                    @else
                        <button wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                wire:loading.attr="disabled"
                                aria-label="Halaman {{ $page }}"
                                class="flex items-center justify-center w-8 h-8 rounded-lg text-xs font-medium
                                       text-zinc-500 dark:text-primary-dark-400
                                       ring-1 ring-zinc-200 dark:ring-primary-dark-700 bg-white dark:bg-primary-dark-800
                                       hover:bg-zinc-50 dark:hover:bg-primary-dark-700 hover:text-zinc-700 dark:hover:text-primary-dark-200
                                       transition-all duration-150">
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <button wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                    class="flex items-center justify-center w-8 h-8 rounded-lg text-zinc-400 dark:text-primary-dark-500
                           ring-1 ring-zinc-200 dark:ring-primary-dark-700 bg-white dark:bg-primary-dark-800
                           hover:bg-zinc-50 dark:hover:bg-primary-dark-700 hover:text-zinc-600 dark:hover:text-primary-dark-300
                           transition-all duration-150"
                    aria-label="Halaman berikutnya">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </button>
        @else
            <span class="flex items-center justify-center w-8 h-8 rounded-lg text-zinc-300 dark:text-primary-dark-700
                         ring-1 ring-zinc-200 dark:ring-primary-dark-700 bg-white dark:bg-primary-dark-800
                         cursor-not-allowed select-none"
                  aria-disabled="true" aria-label="Halaman berikutnya">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </span>
        @endif

    </div>
</nav>
@endif
