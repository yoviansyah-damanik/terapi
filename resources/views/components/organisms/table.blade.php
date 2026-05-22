@props([
    'footer' => null,
])

<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700']) }}>
        @if (!empty($headings))
            <thead class="sticky top-0 z-10 bg-zinc-50 dark:bg-primary-dark-900 shadow-sm">
                <tr>{{ $headings }}</tr>
            </thead>
        @endif

        <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
            {{ $slot }}
        </tbody>
    </table>
</div>

@if (!empty($footer))
    <div class="border-t border-zinc-200 dark:border-primary-dark-700">
        {{ $footer }}
    </div>
@endif
