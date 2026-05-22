{{--
    Pill-style tabs untuk Alpine.js x-data context.
    Usage: <x-molecules.section-tabs model="logTab" :items="[['key'=>'info','label'=>'Info'], ...]" />
--}}
@props([
    'model' => 'tab',
    'items' => [],
])

<div class="flex gap-0.5 rounded-xl bg-zinc-100 p-1 dark:bg-primary-dark-900/50">
    @foreach ($items as $item)
        <button type="button"
            @click="{{ $model }} = '{{ $item['key'] }}'"
            :class="{{ $model }} === '{{ $item['key'] }}'
                ? 'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-800 dark:text-primary-dark-100'
                : 'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 dark:hover:text-primary-dark-200'"
            class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition-all">
            {{ $item['label'] }}
        </button>
    @endforeach
</div>
