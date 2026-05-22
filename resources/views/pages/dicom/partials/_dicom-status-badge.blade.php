@php
    $statusConfig = match($status ?? null) {
        'worklist' => ['color' => 'violet', 'icon' => 'queue-list',   'label' => 'Worklist'],
        'pending'  => ['color' => 'amber',  'icon' => 'clock',        'label' => 'Pending'],
        'received' => ['color' => 'sky',    'icon' => 'inbox-arrow-down', 'label' => 'Diterima'],
        'sent'     => ['color' => 'teal',   'icon' => 'check-badge',  'label' => 'Terkirim'],
        'error'    => ['color' => 'red',    'icon' => 'x-circle',     'label' => 'Error'],
        default    => ['color' => 'zinc',   'icon' => 'minus-circle', 'label' => 'Belum Ada'],
    };
@endphp
<flux:badge color="{{ $statusConfig['color'] }}" size="sm" icon="{{ $statusConfig['icon'] }}">
    {{ $statusConfig['label'] }}
</flux:badge>
