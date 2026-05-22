@props([
    'value' => '',
    'label' => '',
    'active' => false,
])

<label @class([
    'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-sm cursor-pointer select-none transition',
    'border-primary-500 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 dark:border-primary-600' => $active,
    'border-zinc-300 dark:border-primary-dark-600 text-zinc-600 dark:text-primary-dark-400 hover:border-zinc-400 dark:hover:border-primary-dark-500 hover:text-zinc-700 dark:hover:text-primary-dark-300' => !$active,
])>
    <input type="checkbox" value="{{ $value }}" {{ $attributes->merge(['class' => 'sr-only']) }}>
    @if ($active)
        <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
    @endif
    {{ $label }}
</label>
