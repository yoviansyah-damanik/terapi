{{--
    x-atoms.status-badge
    Usage:
    <x-atoms.status-badge status="200" /> -> 200 Sukses
    <x-atoms.status-badge code="500" status="error" /> -> 500 Gagal
--}}
@props(['status', 'code' => null])

@php
    $normalizedStatus = strtolower((string) $status);

    // Auto-detect from code if status string is numeric
    if (is_numeric($status) && !$code) {
        $code = $status;
        $normalizedStatus = $code >= 200 && $code < 300 ? 'success' : ($code >= 400 ? 'failed' : 'info');
    }

    $color = match (true) {
        in_array($normalizedStatus, ['success', 'sukses', 'berhasil', '2xx']) => 'green',
        in_array($normalizedStatus, ['failed', 'gagal', 'error', 'kesalahan', '4xx', '5xx']) => 'red',
        in_array($normalizedStatus, ['warning', 'peringatan', 'pending', 'tertunda']) => 'amber',
        in_array($normalizedStatus, ['info', 'informasi', 'pemberitahuan']) => 'blue',
        default => 'zinc',
    };

    $label = match (true) {
        in_array($normalizedStatus, ['success', 'sukses', 'berhasil', '2xx']) => 'Sukses',
        in_array($normalizedStatus, ['failed', 'gagal', 'error', 'kesalahan', '4xx', '5xx']) => 'Gagal',
        in_array($normalizedStatus, ['warning', 'peringatan']) => 'Peringatan',
        in_array($normalizedStatus, ['pending', 'tertunda']) => 'Tertunda',
        in_array($normalizedStatus, ['info', 'informasi', 'pemberitahuan']) => 'Info',
        default => ucfirst($status),
    };

    $displayText = $code ? $code . ' - ' . $label : $label;
@endphp

<flux:badge :color="$color" {{ $attributes->merge(['size' => 'sm']) }}>{{ $displayText }}</flux:badge>
