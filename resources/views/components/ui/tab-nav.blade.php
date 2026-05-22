@props([
    'tabs' => [], // array of [key, label] pairs
    'active' => '', // current active tab key
    'model' => 'activeTab', // Livewire property name to $set
])

<div class="flex flex-wrap gap-x-1 border-b border-zinc-200 dark:border-primary-dark-700 mb-4">
    @foreach ($tabs as [$key, $label])
        <button wire:click="$set('{{ $model }}', '{{ $key }}')" @class([
            'px-4 py-2 text-sm font-medium transition-colors',
            'text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400' =>
                $active === $key,
            'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-300' =>
                $active !== $key,
        ])>
            {{ $label }}
        </button>
    @endforeach
</div>
