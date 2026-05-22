{{--
    Pill-style tabs-2 untuk Alpine.js x-data context.
    Usage: <x-molecules.tabs-2 model="tabName" :options="['tab1' => 'Label 1', 'tab2' => 'Label 2']" />
--}}
@props([
    'model',
    'options' => [],
])

<div {{ $attributes->merge(['class' => 'flex gap-0.5 rounded-xl bg-zinc-100 p-1 dark:bg-primary-dark-900/50']) }}>
    @foreach ($options as $value => $label)
        <button type="button" @click="{{ $model }} = '{{ $value }}'"
            :class="{{ $model }} === '{{ $value }}' ?
                'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-800 dark:text-primary-dark-100' :
                'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 dark:hover:text-primary-dark-200'"
            class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition-all">
            {{ $label }}
        </button>
    @endforeach
</div>
