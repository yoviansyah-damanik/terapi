@props([
    'value' => '',
    'label' => '',
    'description' => null,
    'active' => false,
])

<label @class([
    'flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition',
    'border-primary-400 bg-primary-50 dark:bg-primary-900/20 dark:border-primary-600' => $active,
    'border-zinc-200 dark:border-primary-dark-700 hover:border-zinc-300 dark:hover:border-primary-dark-600' => !$active,
])>
    <input type="radio" value="{{ $value }}"
        {{ $attributes->merge(['class' => 'mt-0.5 text-primary-600 border-zinc-300 dark:border-primary-dark-600']) }}>
    <div>
        <p @class([
            'text-sm font-medium',
            'text-primary-700 dark:text-primary-300' => $active,
            'text-zinc-700 dark:text-primary-dark-300' => !$active,
        ])>
            {{ $label }}
        </p>
        @if ($description)
            <p class="text-xs text-zinc-500 mt-0.5">{{ $description }}</p>
        @endif
    </div>
</label>
