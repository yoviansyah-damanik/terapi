<div class="space-y-6">
    @include('pages::erm.detail-partials._obat-rawatan-table', [
        'items' => $obatMedis,
        'title' => 'Obat Rawatan',
        'emptyLabel' => 'Tidak ada data obat rawatan',
        'icon' => 'beaker',
    ])
    @if ($reg->status_lanjut === 'Ranap')
        @include('pages::erm.detail-partials._obat-pulang-table', [
            'items' => $resepPulangMedis,
            'title' => 'Obat Pulang',
            'emptyLabel' => 'Tidak ada data obat pulang',
            'icon' => 'beaker',
        ])
    @endif
</div>
