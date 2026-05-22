<div class="space-y-6">
    @include('pages::erm.detail-partials._obat-rawatan-table', [
        'items' => $vaksin,
        'title' => 'Vaksin Rawatan',
        'emptyLabel' => 'Tidak ada data vaksin rawatan',
        'icon' => 'shield-check',
    ])
    @if ($reg->status_lanjut === 'Ranap')
        @include('pages::erm.detail-partials._obat-pulang-table', [
            'items' => $resepPulangVaksin,
            'title' => 'Vaksin Pulang',
            'emptyLabel' => 'Tidak ada data vaksin pulang',
            'icon' => 'shield-check',
        ])
    @endif
</div>
