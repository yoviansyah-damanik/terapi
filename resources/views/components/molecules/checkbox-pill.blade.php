@props([
    'value' => '',
    'label' => '',
    'active' => false,
])

<label @class([
    'inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm cursor-pointer select-none transition-all duration-200 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-1 dark:focus-within:ring-offset-primary-dark-900',
    'bg-primary-100 dark:bg-primary-900/60 text-primary-800 dark:text-primary-300 shadow-sm' => $active,
    'bg-zinc-100 dark:bg-primary-dark-800 text-zinc-600 dark:text-primary-dark-400 hover:bg-zinc-200 dark:hover:bg-primary-dark-700 hover:text-zinc-800 dark:hover:text-primary-dark-200' => !$active,
])>
    <input type="checkbox" value="{{ $value }}" {{ $attributes->merge(['class' => 'sr-only']) }}>
    
    @if ($active)
        <svg class="w-3.5 h-3.5 shrink-0 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
    @else
        {{-- Optional: empty circle for inactive states or leaving it blank. Let's leave blank for pill design --}}
    @endif
    
    <span class="font-medium">{{ $label }}</span>
</label>
