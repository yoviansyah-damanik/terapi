@props([
    'modules'        => [],       // array dari MODULES constant
    'tab'            => '',       // $tab aktif (module key)
    'sections'       => [],       // $sidebarSections saat ini
    'activeSection'  => '',       // $activeSection aktif
    'selectMethod'   => 'selectTab',   // nama method Livewire untuk ganti tab
])

{{-- Desktop Sidebar --}}
<aside class="hidden lg:block w-60 shrink-0">
    <div class="sticky top-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200/60 dark:bg-primary-dark-800 dark:ring-primary-dark-700/60">

        <div class="px-4 pt-4 pb-2">
            <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">Modul</p>
        </div>

        <nav class="px-2 pb-2 space-y-0.5">
            @foreach ($modules as $moduleKey => $module)
                @php $isActive = $tab === $moduleKey; @endphp

                <button wire:click="{{ $selectMethod }}('{{ $moduleKey }}')"
                    class="group flex items-center w-full gap-2.5 px-3 py-2 rounded-xl text-sm font-medium transition-colors
                        {{ $isActive
                            ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
                            : 'text-zinc-600 dark:text-primary-dark-400 hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 hover:text-zinc-800 dark:hover:text-primary-dark-200' }}">
                    <flux:icon name="{{ $module['icon'] }}"
                        class="w-4 h-4 shrink-0 {{ $isActive ? 'text-primary-600 dark:text-primary-400' : 'text-zinc-400 dark:text-primary-dark-500 group-hover:text-zinc-500 dark:group-hover:text-primary-dark-400' }}" />
                    <span class="flex-1 text-left">{{ $module['label'] }}</span>
                    @if ($isActive)
                        <div class="w-1.5 h-1.5 rounded-full bg-primary-500 dark:bg-primary-400 shrink-0"></div>
                    @endif
                </button>

                @if ($isActive && count($sections) > 0)
                    <div class="ml-3 pl-3 border-l border-zinc-200 dark:border-primary-dark-700 space-y-0.5 pb-1">
                        @foreach ($sections as $sectionKey => $section)
                            <button wire:click="$set('activeSection', '{{ $sectionKey }}')"
                                class="flex items-center w-full gap-2 px-2.5 py-1.5 rounded-lg text-xs transition-colors
                                    {{ $activeSection === $sectionKey
                                        ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 font-semibold'
                                        : 'text-zinc-500 dark:text-primary-dark-400 hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 hover:text-zinc-700 dark:hover:text-primary-dark-300' }}">
                                <flux:icon name="{{ $section['icon'] }}" class="w-3.5 h-3.5 shrink-0 opacity-70" />
                                {{ $section['label'] }}
                            </button>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>
    </div>
</aside>

{{-- Mobile: module + section nav --}}
<div class="lg:hidden mb-4 w-full">
    <div class="flex flex-wrap gap-2">
        @foreach ($modules as $moduleKey => $module)
            <x-atoms.button icon="{{ $module['icon'] }}" wire:click="{{ $selectMethod }}('{{ $moduleKey }}')"
                :variant="$tab === $moduleKey ? 'primary' : 'ghost'" size="sm">
                {{ $module['label'] }}
            </x-atoms.button>
        @endforeach
    </div>
    @if (count($sections) > 1)
        <div class="flex flex-wrap gap-1.5 mt-3">
            @foreach ($sections as $sectionKey => $section)
                <button wire:click="$set('activeSection', '{{ $sectionKey }}')"
                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs transition-colors
                        {{ $activeSection === $sectionKey
                            ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 font-medium'
                            : 'bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-400' }}">
                    <flux:icon name="{{ $section['icon'] }}" class="w-3 h-3" />
                    {{ $section['label'] }}
                </button>
            @endforeach
        </div>
    @endif
</div>
