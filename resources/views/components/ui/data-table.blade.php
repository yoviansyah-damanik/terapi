@props([
    'columns' => [],
    'striped' => false,
    'hoverable' => true,
])

<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <flux:table :striped="$striped" :hoverable="$hoverable">
        @if(count($columns) > 0)
        <flux:table.columns>
            @foreach($columns as $column)
            <flux:table.column
                :sortable="$column['sortable'] ?? false"
                :align="$column['align'] ?? 'left'"
            >
                {{ $column['label'] ?? $column }}
            </flux:table.column>
            @endforeach
        </flux:table.columns>
        @elseif(isset($header))
        <flux:table.columns>
            {{ $header }}
        </flux:table.columns>
        @endif

        <flux:table.rows>
            {{ $slot }}
        </flux:table.rows>
    </flux:table>
</div>
