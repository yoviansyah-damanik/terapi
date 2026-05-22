@props([
    'rl' => '',
    'tableId' => 'report-table',
])

<div class="flex flex-wrap gap-2 mt-4 print:hidden" x-data="{ exporting: false }">
    <flux:button icon="printer" variant="filled" size="sm" onclick="window.print()">
        Cetak
    </flux:button>

    <flux:button icon="arrow-down-tray" variant="outline" size="sm"
        x-on:click="exporting = true; exportSirsPDF().finally(() => exporting = false)"
        x-bind:disabled="exporting">
        <span x-text="exporting ? 'Memproses...' : 'Export PDF'"></span>
    </flux:button>
</div>

@pushOnce('scripts')
<script src="https://cdn.jsdelivr.net/npm/html-to-image@1.11.11/dist/html-to-image.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
/**
 * Mengekspor elemen [data-area-print] ke file PDF landscape A4.
 * Menggunakan html-to-image (bukan html2canvas) agar warna oklch Tailwind v4
 * di-render natively oleh browser via SVG foreignObject.
 */
async function exportSirsPDF() {
    const areas = document.querySelectorAll('[data-area-print]');
    if (!areas.length) return;

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    const margin = 10;
    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();
    const usableW = pageW - margin * 2;

    let y = margin;
    let firstPage = true;

    for (const area of areas) {
        // Ambil dimensi penuh termasuk konten yang ter-overflow secara horizontal
        const fullWidth = area.scrollWidth;
        const fullHeight = area.scrollHeight;

        const imgData = await htmlToImage.toJpeg(area, {
            quality: 0.92,
            backgroundColor: '#ffffff',
            width: fullWidth,
            height: fullHeight,
            style: { overflow: 'visible' },
        });

        const imgW = usableW;
        const imgH = imgW * (fullHeight / fullWidth);

        if (!firstPage && y + imgH > pageH - margin) {
            pdf.addPage();
            y = margin;
        }

        pdf.addImage(imgData, 'JPEG', margin, y, imgW, imgH);
        y += imgH + 5;
        firstPage = false;
    }

    const title = document.querySelector('[data-area-print] h1')
        ?.textContent?.trim()
        ?.replace(/[^\w\s-]/g, '') ?? 'laporan-sirs';

    pdf.save(`${title}.pdf`);
}
</script>
@endPushOnce
