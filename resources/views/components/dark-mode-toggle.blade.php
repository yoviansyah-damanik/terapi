@props(['variant' => 'button'])

@php
    $toggleVariant = $variant;
@endphp

<div x-data="{
    darkMode: localStorage.getItem('darkMode') === 'true',
    init() {
        this.applyTheme();
        this.$watch('darkMode', (val) => {
            localStorage.setItem('darkMode', val);
            this.applyTheme();
        });
    },
    applyTheme() {
        if (this.darkMode) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    },
    toggle() {
        this.darkMode = !this.darkMode;
    }
}" {{ $attributes->except('variant') }}>
    @if ($toggleVariant === 'button')
        <flux:button @click="toggle()" variant="subtle" size="sm" x-show="!darkMode">
            <flux:icon name="moon" variant="outline" class="size-5" />
        </flux:button>
        <flux:button @click="toggle()" variant="subtle" size="sm" x-show="darkMode" x-cloak>
            <flux:icon name="sun" variant="outline" class="size-5" />
        </flux:button>
    @elseif($toggleVariant === 'switch')
        <div class="flex items-center gap-2">
            <flux:icon name="sun" variant="outline" class="size-4 text-zinc-400"
                x-bind:class="darkMode ? 'text-zinc-600' : 'text-secondary-500'" />
            <button @click="toggle()" type="button" role="switch" :aria-checked="darkMode"
                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                :class="darkMode ? 'bg-primary-500' : 'bg-zinc-300'">
                <span aria-hidden="true"
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    :class="darkMode ? 'translate-x-5' : 'translate-x-0'"></span>
            </button>
            <flux:icon name="moon" variant="outline" class="size-4 text-zinc-400"
                x-bind:class="darkMode ? 'text-secondary-500' : 'text-zinc-600'" />
        </div>
    @elseif($toggleVariant === 'sidebar')
        <button @click="toggle()" type="button"
            class="flex items-center w-full gap-3 px-3 py-2 text-sm transition-colors rounded-lg text-white/80 hover:text-white hover:bg-white/10">
            {{-- Moon icon --}}
            <svg x-show="!darkMode" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke-width="1.5" stroke="currentColor" class="size-5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
            </svg>
            {{-- Sun icon --}}
            <svg x-show="darkMode" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke-width="1.5" stroke="currentColor" class="size-5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
            </svg>
            <span x-text="darkMode ? 'Mode Terang' : 'Mode Gelap'"></span>
        </button>
    @endif
</div>
