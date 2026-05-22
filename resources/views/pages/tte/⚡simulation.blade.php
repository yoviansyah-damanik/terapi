<?php

use App\Helpers\ConfigurationHelper;
use App\Helpers\QrCodeHelper;
use App\Models\TteDocument;
use App\Services\TteService;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app', ['title' => 'Simulasi TTE'])] class extends Component {
    public string $activeTab = 'sign';
    public string $simulationFile = 'tte-simulation.pdf';

    // --- PDF Dimensions ---
    public ?float $pdfWidth = null;
    public ?float $pdfHeight = null;
    public ?int $pdfPageCount = 0;

    public float $defaultOriginX = 0;
    public float $defaultOriginY = 0;
    public float $defaultWidth = 80;
    public float $defaultHeight = 80;

    // --- Tab Simulasi Sign ---
    public string $simNik = '';
    public string $simPassphrase = '';
    public string $simTampilan = 'VISIBLE';
    public string $simImageType = 'coordinate'; // 'coordinate' | 'tag'
    public int $simPage = 1;
    public float $simOriginX = 0;
    public float $simOriginY = 0;
    public float $simWidth = 80;
    public float $simHeight = 80;
    public string $simTagKoordinat = '#';
    public string $simLocation = 'Jakarta';
    public string $simReason = '';
    public ?array $simResult = null;
    public ?array $simRequest = null;

    // --- Tab Simulasi Seal ---
    public string $sealIdSubscriber = '';
    public string $sealTotp = '';
    public string $sealTampilan = 'VISIBLE';
    public string $sealImageType = 'coordinate'; // 'coordinate' | 'tag'
    public int $sealPage = 1;
    public float $sealOriginX = 0;
    public float $sealOriginY = 0;
    public float $sealWidth = 80;
    public float $sealHeight = 80;
    public string $sealTagKoordinat = '#';
    public string $sealLocation = '';
    public string $sealReason = '';
    public ?array $sealResult = null;
    public ?array $sealRequest = null;

    // --- QR Preview ---
    public ?string $qrDataUri = null;

    public function mount(): void
    {
        $this->simNik = ConfigurationHelper::get('tte.default_nik', '');
        $this->simPassphrase = ConfigurationHelper::get('tte.default_passphrase', '');
        $this->loadQrDefaults();
        $this->generateQrPreview();
    }

    /**
     * Muat ukuran default QR dari konfigurasi
     */
    private function loadQrDefaults(): void
    {
        $widthMm = (float) ConfigurationHelper::get('tte.qr.width_mm', '25');
        $heightMm = (float) ConfigurationHelper::get('tte.qr.height_mm', '25');

        $widthPt = round($widthMm * 2.8346, 2);
        $heightPt = round($heightMm * 2.8346, 2);

        $this->simWidth = $widthPt;
        $this->simHeight = $heightPt;
        $this->sealWidth = $widthPt;
        $this->sealHeight = $heightPt;
    }

    /**
     * Generate preview QR menggunakan seluruh konfigurasi tte.qr.*
     */
    public function generateQrPreview(): void
    {
        try {
            $template = ConfigurationHelper::get('tte.qr.content_template', '{app_name} - Ditandatangani secara elektronik pada {signed_at}');
            $sampleData = str_replace(['{app_name}', '{signer_name}', '{signed_at}', '{document_number}'], [config('app.name', 'Suratin'), 'John Doe', now()->format('d/m/Y H:i'), 'DOC-2024-001'], $template);

            $result = QrCodeHelper::generate($sampleData, 'png', [
                'error_correction' => ConfigurationHelper::get('tte.qr.error_correction', 'H'),
                'foreground_color' => ConfigurationHelper::get('tte.qr.foreground_color', '#000000'),
                'background_color' => ConfigurationHelper::get('tte.qr.background_color', '#FFFFFF'),
                'round_block_mode' => ConfigurationHelper::get('tte.qr.round_block_mode', 'margin'),
                'logo_enabled' => ConfigurationHelper::get('tte.qr.logo_enabled', '0') === '1',
                'logo_path' => ConfigurationHelper::get('tte.qr.logo_path'),
                'logo_size' => (int) ConfigurationHelper::get('tte.qr.logo_size', '60'),
            ]);

            $this->qrDataUri = $result->getDataUri();
        } catch (\Throwable $e) {
            $this->qrDataUri = null;
        }
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Update dimensi PDF dari hasil parsing frontend
     */
    public function updatePdfDimensions(float $width, float $height, int $pageCount): void
    {
        $this->pdfWidth = round($width, 2);
        $this->pdfHeight = round($height, 2);
        $this->pdfPageCount = $pageCount;

        // Reset halaman ke 1
        $this->simPage = 1;
        $this->sealPage = 1;

        // Reset ukuran QR ke default dari konfigurasi (25mm = ~70.87pt)
        $this->loadQrDefaults();
        $this->simOriginX = $this->defaultOriginX;
        $this->simOriginY = $this->defaultOriginY;
        $this->simHeight = $this->defaultHeight;
        $this->simWidth = $this->defaultWidth;
        $this->sealOriginX = $this->defaultOriginX;
        $this->sealOriginY = $this->defaultOriginY;
        $this->sealHeight = $this->defaultHeight;
        $this->sealWidth = $this->defaultWidth;

        // Log untuk debug
        logger()->info('PDF dimensions updated and coordinates reset', [
            'pdf_size' => "{$width}x{$height}",
            'pages' => $pageCount,
            'sim' => [
                'page' => $this->simPage,
                'origin' => "{$this->simOriginX},{$this->simOriginY}",
                'size' => "{$this->simWidth}x{$this->simHeight}",
            ],
            'seal' => [
                'page' => $this->sealPage,
                'origin' => "{$this->sealOriginX},{$this->sealOriginY}",
                'size' => "{$this->sealWidth}x{$this->sealHeight}",
            ],
        ]);
    }

    /**
     * Update dimensi PDF saat ganti halaman (tanpa reset koordinat QR)
     */
    public function updatePageDimensions(float $width, float $height): void
    {
        $this->pdfWidth = round($width, 2);
        $this->pdfHeight = round($height, 2);
    }

    /**
     * Download hasil simulasi sign
     */
    public function downloadSimSign(): void
    {
        if (isset($this->simResult['data']['file']) && !empty($this->simResult['data']['file'])) {
            $timestamp = $this->simResult['data']['time'] ?? now()->format('YmdHis');
            $this->dispatch('download-pdf', file: $this->simResult['data']['file'], filename: "sign_simulation_{$timestamp}.pdf");
        }
    }

    /**
     * Download hasil simulasi seal
     */
    public function downloadSimSeal(): void
    {
        if (isset($this->sealResult['data']['file']) && !empty($this->sealResult['data']['file'])) {
            $timestamp = $this->sealResult['data']['time'] ?? now()->format('YmdHis');
            $this->dispatch('download-pdf', file: $this->sealResult['data']['file'], filename: "seal_simulation_{$timestamp}.pdf");
        }
    }

    /** Dispatch event ke Alpine saat file simulasi diganti */
    public function updatedSimulationFile(): void
    {
        $this->dispatch('load-simulation-pdf', url: route('tte.simulation-pdf', ['file' => $this->simulationFile]));
    }

    /** Baca file simulasi PDF dan konversi ke base64 */
    private function getPdfBase64(): ?string
    {
        $path = resource_path('files/' . $this->simulationFile);
        return file_exists($path) ? base64_encode(file_get_contents($path)) : null;
    }

    /**
     * Simulasi tanda tangan PDF (visible/invisible)
     */
    public function simulateSign(): void
    {
        $this->validate([
            'simNik' => 'required|string',
            'simPassphrase' => 'required|string',
            'simTampilan' => 'required|in:VISIBLE,INVISIBLE',
            'simPage' => 'required|integer|min:1',
            'simLocation' => 'nullable|string',
            'simReason' => 'nullable|string',
        ]);

        $this->simResult = null;

        $signatureProperties = [
            [
                'tampilan' => $this->simTampilan,
                'location' => $this->simLocation,
                'reason' => $this->simReason,
            ],
        ];

        if ($this->simTampilan === 'VISIBLE') {
            if ($this->simImageType === 'tag') {
                $signatureProperties[0]['tag'] = $this->simTagKoordinat;
                if ($this->qrDataUri) {
                    $signatureProperties[0]['imageBase64'] = str_replace('data:image/png;base64,', '', $this->qrDataUri);
                }
                $signatureProperties[0]['width'] = $this->simWidth;
                $signatureProperties[0]['height'] = $this->simHeight;
            } else {
                if ($this->qrDataUri) {
                    $signatureProperties[0]['imageBase64'] = str_replace('data:image/png;base64,', '', $this->qrDataUri);
                }
                $signatureProperties[0]['page'] = $this->simPage;
                $signatureProperties[0]['originX'] = $this->simOriginX;
                $signatureProperties[0]['originY'] = $this->simOriginY;
                $signatureProperties[0]['width'] = $this->simWidth;
                $signatureProperties[0]['height'] = $this->simHeight;
            }
        }

        $files = [];
        $base64 = $this->getPdfBase64();
        if ($base64) {
            $files[] = $base64;
        }

        // Capture request data (tanpa base64 untuk hemat memory)
        $this->simRequest = [
            'nik' => $this->simNik,
            'passphrase' => '***hidden***',
            'signatureProperties' => $signatureProperties,
            'file' => $files ? ['<base64_pdf_' . strlen($files[0]) . '_bytes>'] : [],
        ];

        try {
            $service = app(TteService::class);
            $this->simResult = $service->signPdf([
                'nik' => $this->simNik,
                'passphrase' => $this->simPassphrase,
                'signatureProperties' => $signatureProperties,
                'file' => $files,
            ]);

            if ($this->simResult['success']) {
                $mode = $this->simTampilan === 'INVISIBLE' ? 'invisible' : $this->simImageType;
                TteDocument::createFromSimulation('sign_pdf', $this->simNik, $mode, $this->simResult);
                $this->toastSuccess('Simulasi sign berhasil dikirim');
                $this->dispatch('sign-result-ready');
            } else {
                $this->toastWarning('Server TTE merespon dengan error');
            }
        } catch (\Throwable $e) {
            $this->simResult = [
                'success' => false,
                'data' => ['error' => $e->getMessage()],
            ];
            $this->toastError('Gagal mengirim simulasi: ' . $e->getMessage());
        }
    }

    /**
     * Simulasi seal PDF
     */
    public function simulateSeal(): void
    {
        $this->validate([
            'sealIdSubscriber' => 'required|string',
            'sealTotp' => 'required|string',
            'sealTampilan' => 'required|in:VISIBLE,INVISIBLE',
            'sealLocation' => 'nullable|string',
            'sealReason' => 'nullable|string',
        ]);

        $this->sealResult = null;

        $signatureProperties = [
            [
                'tampilan' => $this->sealTampilan,
                'location' => $this->sealLocation,
                'reason' => $this->sealReason,
            ],
        ];

        if ($this->sealTampilan === 'VISIBLE') {
            if ($this->sealImageType === 'tag') {
                $signatureProperties[0]['tag'] = $this->sealTagKoordinat;
                if ($this->qrDataUri) {
                    $signatureProperties[0]['imageBase64'] = str_replace('data:image/png;base64,', '', $this->qrDataUri);
                }
                $signatureProperties[0]['width'] = $this->sealWidth;
                $signatureProperties[0]['height'] = $this->sealHeight;
            } else {
                if ($this->qrDataUri) {
                    $signatureProperties[0]['imageBase64'] = str_replace('data:image/png;base64,', '', $this->qrDataUri);
                }
                $signatureProperties[0]['page'] = $this->sealPage;
                $signatureProperties[0]['originX'] = $this->sealOriginX;
                $signatureProperties[0]['originY'] = $this->sealOriginY;
                $signatureProperties[0]['width'] = $this->sealWidth;
                $signatureProperties[0]['height'] = $this->sealHeight;
            }
        }

        $files = [];
        $base64 = $this->getPdfBase64();
        if ($base64) {
            $files[] = $base64;
        }

        // Capture request data (tanpa base64 untuk hemat memory)
        $this->sealRequest = [
            'idSubscriber' => $this->sealIdSubscriber,
            'totp' => '***hidden***',
            'signatureProperties' => $signatureProperties,
            'file' => $files ? ['<base64_pdf_' . strlen($files[0]) . '_bytes>'] : [],
        ];

        try {
            $service = app(TteService::class);
            $this->sealResult = $service->sealPdf(idSubscriber: $this->sealIdSubscriber, totp: $this->sealTotp, signatureProperties: $signatureProperties, files: $files);

            if ($this->sealResult['success']) {
                $mode = $this->sealTampilan === 'INVISIBLE' ? 'invisible' : $this->sealImageType;
                TteDocument::createFromSimulation('seal_pdf', null, $mode, $this->sealResult);
                $this->toastSuccess('Simulasi seal berhasil dikirim');
                $this->dispatch('seal-result-ready');
            } else {
                $this->toastWarning('Server TTE merespon dengan error');
            }
        } catch (\Throwable $e) {
            $this->sealResult = [
                'success' => false,
                'data' => ['error' => $e->getMessage()],
            ];
            $this->toastError('Gagal mengirim simulasi: ' . $e->getMessage());
        }
    }

    /** Preview live request payload sign sebelum dikirim */
    private function buildSignPreview(): array
    {
        $sp = ['tampilan' => $this->simTampilan, 'location' => $this->simLocation ?: null, 'reason' => $this->simReason ?: null];
        if ($this->simTampilan === 'VISIBLE') {
            if ($this->simImageType === 'tag') {
                $sp['tag'] = $this->simTagKoordinat;
                $sp['imageBase64'] = '<base64_qr_image>';
                $sp['width'] = $this->simWidth;
                $sp['height'] = $this->simHeight;
            } else {
                $sp['imageBase64'] = '<base64_qr_image>';
                $sp['page'] = $this->simPage;
                $sp['originX'] = $this->simOriginX;
                $sp['originY'] = $this->simOriginY;
                $sp['width'] = $this->simWidth;
                $sp['height'] = $this->simHeight;
            }
        }
        return [
            'nik' => $this->simNik ?: '<nik>',
            'passphrase' => '***',
            'signatureProperties' => [$sp],
            'file' => ['<base64_pdf>'],
        ];
    }

    /** Preview live request payload seal sebelum dikirim */
    private function buildSealPreview(): array
    {
        $sp = ['tampilan' => $this->sealTampilan, 'location' => $this->sealLocation ?: null, 'reason' => $this->sealReason ?: null];
        if ($this->sealTampilan === 'VISIBLE') {
            if ($this->sealImageType === 'tag') {
                $sp['tag'] = $this->sealTagKoordinat;
                $sp['imageBase64'] = '<base64_qr_image>';
                $sp['width'] = $this->sealWidth;
                $sp['height'] = $this->sealHeight;
            } else {
                $sp['imageBase64'] = '<base64_qr_image>';
                $sp['page'] = $this->sealPage;
                $sp['originX'] = $this->sealOriginX;
                $sp['originY'] = $this->sealOriginY;
                $sp['width'] = $this->sealWidth;
                $sp['height'] = $this->sealHeight;
            }
        }
        return [
            'idSubscriber' => $this->sealIdSubscriber ?: '<id_subscriber>',
            'totp' => '***',
            'signatureProperties' => [$sp],
            'file' => ['<base64_pdf>'],
        ];
    }

    public function with(): array
    {
        return [
            'simPreviewRequest' => $this->buildSignPreview(),
            'sealPreviewRequest' => $this->buildSealPreview(),
        ];
    }
};
?>

<div x-data="{
    pdfBlobUrl: null,
    pdfLoaded: false,
    pdfDimensions: null, // Store PDF dimensions locally { width, height }
    currentPage: @entangle('simPage').live, // Track current page for Sign tab
    sealPage: @entangle('sealPage').live, // Track current page for Seal tab
    isRenderingSign: false, // Flag to prevent concurrent renders on Sign canvas
    isRenderingSeal: false, // Flag to prevent concurrent renders on Seal canvas
    lastRenderedSignPage: null, // Track last rendered page to prevent re-render
    lastRenderedSealPage: null, // Track last rendered page to prevent re-render
    isInitialLoad: false, // Flag to prevent x-effect during initial load
    signResultBlobUrl: null,
    sealResultBlobUrl: null,

    base64ToBlobUrl(b64) {
        try {
            const binary = atob(b64);
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
            return URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
        } catch { return null; }
    },

    handleSignSuccess() {
        const file = @this.simResult?.data?.file;
        if (this.signResultBlobUrl) URL.revokeObjectURL(this.signResultBlobUrl);
        this.signResultBlobUrl = file ? this.base64ToBlobUrl(file) : null;
        this.$nextTick(() => $flux.modal('sign-result').show());
    },

    handleSealSuccess() {
        const file = @this.sealResult?.data?.file;
        if (this.sealResultBlobUrl) URL.revokeObjectURL(this.sealResultBlobUrl);
        this.sealResultBlobUrl = file ? this.base64ToBlobUrl(file) : null;
        this.$nextTick(() => $flux.modal('seal-result').show());
    },

    async init() {
        console.log('🚀 Alpine component initialized');
        await this.loadPdfFromUrl('{{ route('tte.simulation-pdf') }}');
    },

    async loadPdfFromUrl(url) {
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const arrayBuffer = await response.arrayBuffer();

            const blob = new Blob([arrayBuffer], { type: 'application/pdf' });
            if (this.pdfBlobUrl) URL.revokeObjectURL(this.pdfBlobUrl.split('#')[0]);
            this.pdfBlobUrl = URL.createObjectURL(blob);

            const pdfjsLib = await this.initPdfJs();
            if (!pdfjsLib) throw new Error('Failed to load PDF.js');

            window.currentPdfDocument = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
            const page = await window.currentPdfDocument.getPage(1);
            const viewport = page.getViewport({ scale: 1.0 });

            this.pdfDimensions = { width: viewport.width, height: viewport.height };
            this.isInitialLoad = true;
            this.currentPage = 1;

            await @this.call('updatePdfDimensions', viewport.width, viewport.height, window.currentPdfDocument.numPages);

            setTimeout(() => {
                this.pdfLoaded = true;
                this.$nextTick(() => {
                    setTimeout(() => {
                        if (this.$refs.pdfCanvas) {
                            this.renderPdfToCanvas(this.$refs.pdfCanvas, 1, true).then(() => {
                                this.lastRenderedSignPage = 1;
                                this.isInitialLoad = false;
                            });
                        }
                    }, 50);
                });
            }, 200);
        } catch (error) {
            console.error('❌ Error loading simulation PDF:', error);
        }
    },

    async initPdfJs() {
        if (window.pdfJsLib) return window.pdfJsLib;

        try {
            window.pdfJsLib = await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.2.67/build/pdf.min.mjs');
            window.pdfJsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.2.67/build/pdf.worker.min.mjs';
            console.log('✅ PDF.js library loaded');
            return window.pdfJsLib;
        } catch (error) {
            console.error('❌ Error loading PDF.js:', error);
            return null;
        }
    },

    async renderPdfToCanvas(canvasRef, pageNum, isSignTab = true) {
        if (!window.currentPdfDocument || !canvasRef) {
            console.log('❌ Cannot render: missing requirements', {
                hasDoc: !!window.currentPdfDocument,
                hasCanvas: !!canvasRef
            });
            return;
        }

        // Check if already rendering on this canvas
        const renderingFlag = isSignTab ? 'isRenderingSign' : 'isRenderingSeal';
        if (this[renderingFlag]) {
            console.log('⏸️ Skipping render - already rendering on', isSignTab ? 'Sign' : 'Seal', 'canvas');
            return;
        }

        this[renderingFlag] = true;

        try {
            console.log('🎨 Rendering page', pageNum, 'to canvas...');

            const page = await window.currentPdfDocument.getPage(pageNum);

            // Viewport dengan scale 1.0 = ukuran PDF dalam points
            const baseViewport = page.getViewport({ scale: 1.0 });

            // Update dimensi halaman di Alpine state
            this.pdfDimensions = {
                width: baseViewport.width,
                height: baseViewport.height
            };

            // Update dimensi ke Livewire (untuk QR positioner)
            @this.call('updatePageDimensions', baseViewport.width, baseViewport.height);

            // Device pixel ratio untuk rendering tajam di high-DPI display
            const pixelRatio = window.devicePixelRatio || 1;

            // Viewport untuk rendering internal (dengan pixel ratio)
            const renderViewport = page.getViewport({ scale: pixelRatio });

            // Set canvas display size = ukuran PDF dalam points (CSS pixels)
            // Ini memastikan 1 point PDF = 1 CSS pixel di canvas
            canvasRef.style.width = baseViewport.width + 'px';
            canvasRef.style.height = baseViewport.height + 'px';

            // Set canvas internal resolution (buffer) = ukuran render dengan pixel ratio
            // Ini membuat rendering tajam di high-DPI display
            canvasRef.width = renderViewport.width;
            canvasRef.height = renderViewport.height;

            // Clear canvas sebelum render
            const context = canvasRef.getContext('2d');
            context.clearRect(0, 0, canvasRef.width, canvasRef.height);

            // Render PDF ke canvas dengan viewport yang di-scale
            await page.render({
                canvasContext: context,
                viewport: renderViewport
            }).promise;

            console.log('✅ Page', pageNum, 'rendered. PDF:', baseViewport.width.toFixed(2), 'x', baseViewport.height.toFixed(2), 'pt');
        } catch (error) {
            console.error('❌ Render error:', error);
        } finally {
            this[renderingFlag] = false;
        }
    },

    switchPage(pageNum, isSignTab = true) {
        console.log('📄 switchPage called with:', { pageNum, isSignTab, type: typeof pageNum });

        // Convert to number untuk safety
        const targetPage = Number(pageNum);
        console.log('📄 Target page (converted):', targetPage);

        if (isSignTab) {
            // Update halaman di Alpine dan Livewire
            this.currentPage = targetPage;
            this.lastRenderedSignPage = targetPage;
            @this.simPage = targetPage;

            // Reset koordinat QR ke default (karena ukuran halaman mungkin berbeda)
            @this.simOriginX = @this.defaultOriginX;
            @this.simOriginY = @this.defaultOriginY;
            @this.simWidth = @this.defaultWidth;
            @this.simHeight = @this.defaultHeight;

            // Render halaman baru (akan update dimensi juga)
            setTimeout(() => {
                if (this.$refs.pdfCanvas && window.currentPdfDocument) {
                    this.renderPdfToCanvas(this.$refs.pdfCanvas, targetPage, true);
                }
            }, 10);
        } else {
            // Update halaman di Alpine dan Livewire
            this.sealPage = targetPage;
            this.lastRenderedSealPage = targetPage;
            @this.sealPage = targetPage;

            // Reset koordinat QR ke default (karena ukuran halaman mungkin berbeda)
            @this.sealOriginX = @this.defaultOriginX;
            @this.sealOriginY = @this.defaultOriginY;
            @this.sealWidth = @this.defaultWidth;
            @this.sealHeight = @this.defaultHeight;

            // Render halaman baru (akan update dimensi juga)
            setTimeout(() => {
                if (this.$refs.pdfCanvasSeal && window.currentPdfDocument) {
                    this.renderPdfToCanvas(this.$refs.pdfCanvasSeal, targetPage, false);
                }
            }, 10);
        }
    },

    reloadPdf(event) {
        const { url } = event.detail;
        if (!url) return;
        this.pdfLoaded = false;
        this.pdfDimensions = null;
        this.lastRenderedSignPage = null;
        this.lastRenderedSealPage = null;
        this.currentPage = 1;
        this.sealPage = 1;
        this.loadPdfFromUrl(url);
    },

    downloadPdf(event) {
        const { file, filename } = event.detail;
        if (!file) return;

        try {
            // Decode base64 ke binary
            const binaryString = atob(file);
            const bytes = new Uint8Array(binaryString.length);
            for (let i = 0; i < binaryString.length; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }

            // Create blob dan trigger download
            const blob = new Blob([bytes], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename || 'signed_document.pdf';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        } catch (error) {
            console.error('Error downloading PDF:', error);
        }
    }
}" @download-pdf.window="downloadPdf($event)" @load-simulation-pdf.window="reloadPdf($event)"
    @sign-result-ready.window="handleSignSuccess()" @seal-result-ready.window="handleSealSuccess()">
    {{-- Header --}}
    <x-ui.page-header title="Simulasi TTE"
        subtitle="Upload PDF, atur posisi QR, dan kirim simulasi tanda
                tangan / seal" />


    {{-- Tabs --}}
    <x-molecules.tabs>
        <x-atoms.tab-item wire:click="switchTab('sign')" :active="$activeTab === 'sign'">Simulasi Sign</x-atoms.tab-item>
        <x-atoms.tab-item wire:click="switchTab('seal')" :active="$activeTab === 'seal'">Simulasi Seal</x-atoms.tab-item>
    </x-molecules.tabs>

    {{-- File Simulasi --}}
    <x-organisms.card :padding="false" class="mb-6">
    <div class="px-4 py-3 flex flex-wrap items-center gap-3">
        {{-- Status muat --}}
        <div x-show="!pdfLoaded" class="flex items-center gap-2 text-sm text-zinc-400 dark:text-primary-dark-500">
            <flux:icon name="arrow-path" class="animate-spin w-4 h-4 shrink-0" />
            Memuat file simulasi...
        </div>
        <div x-show="pdfLoaded" x-cloak class="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-400">
            <flux:icon name="document-check" class="w-5 h-5 shrink-0" />
            <span>File simulasi dimuat —
                <span class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">{{ $simulationFile }}</span>
            </span>
        </div>

        {{-- Pilihan file --}}
        <div class="flex items-center gap-1.5 ml-auto">
            @foreach (['tte-simulation.pdf', 'tte-simulation-2.pdf'] as $file)
                <label class="cursor-pointer">
                    <input type="radio" wire:model.live="simulationFile" value="{{ $file }}"
                        class="sr-only peer" />
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md border text-xs font-mono transition-colors
                        peer-checked:bg-primary-500 peer-checked:text-white peer-checked:border-primary-500
                        border-zinc-300 dark:border-primary-dark-600 text-zinc-600 dark:text-primary-dark-400
                        hover:border-primary-400">
                        <flux:icon name="document" class="w-3 h-3" />
                        {{ $file }}
                    </span>
                </label>
            @endforeach
        </div>

        @if ($pdfWidth && $pdfHeight)
            <span class="text-xs text-zinc-400 dark:text-primary-dark-500 font-mono">
                {{ number_format($pdfWidth, 0) }} × {{ number_format($pdfHeight, 0) }} pt
                @if ($pdfPageCount)
                    · {{ $pdfPageCount }} hal.
                @endif
            </span>
        @endif
    </div>
    </x-organisms.card>

    {{-- ==================== TAB: SIMULASI SIGN ==================== --}}
    @if ($activeTab === 'sign')
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Kolom Kiri: PDF Preview + QR Positioner --}}
            <div class="xl:col-span-2 space-y-6">
                {{-- PDF Preview dengan QR Overlay --}}
                @if ($simTampilan === 'VISIBLE')
                    <x-organisms.card wire:key="preview-visible" x-show="pdfBlobUrl" x-cloak x-init="lastRenderedSignPage = null">
                        <div class="flex items-center justify-between lg:flex-row flex-col mb-4">
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-primary-dark-100">Preview & Posisi QR</h3>
                            <div class="flex items-center gap-3 text-xs text-zinc-500 dark:text-primary-dark-400">
                                @if ($pdfPageCount)
                                    <div>
                                        <span>Halaman: </span>
                                        <code
                                            class="px-1.5 py-0.5 rounded bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 font-semibold">
                                            {{ $simPage }} / {{ $pdfPageCount }}
                                        </code>
                                    </div>
                                @endif
                                @if ($pdfWidth && $pdfHeight)
                                    <div>
                                        <span>Ukuran: </span>
                                        <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700">
                                            {{ number_format($pdfWidth, 2) }} × {{ number_format($pdfHeight, 2) }}
                                            pt
                                        </code>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Container PDF + QR (Parent Scope) --}}
                        <div class="relative flex flex-col lg:flex-row gap-4">
                            {{-- PDF Preview Container --}}
                            <div class="w-full">
                                <div
                                    class="relative h-full max-h-[600px] overflow-auto border rounded-lg border-zinc-300 dark:border-primary-dark-600 bg-zinc-100 dark:bg-primary-dark-900 w-full">

                                    {{-- Canvas Wrapper - match canvas size exactly --}}
                                    <div class="min-w-full flex items-center justify-center">
                                        <div class="relative" x-ref="canvasWrapper" wire:ignore>
                                            {{-- PDF Canvas Preview --}}
                                            <canvas x-ref="pdfCanvas" x-show="pdfBlobUrl"
                                                class="block pointer-events-none"
                                                x-effect="
                                                    console.log('🔍 x-effect check:', { isInitialLoad, pdfLoaded, currentPage, lastRenderedSignPage, hasCanvas: !!$refs.pdfCanvas });
                                                    if (!isInitialLoad && pdfLoaded && currentPage && currentPage > 0 && $refs.pdfCanvas) {
                                                        if (lastRenderedSignPage !== currentPage) {
                                                            console.log('🔄 x-effect: Rendering Sign page:', currentPage, '(previous:', lastRenderedSignPage, ')');
                                                            renderPdfToCanvas($refs.pdfCanvas, currentPage, true).then(() => {
                                                                lastRenderedSignPage = currentPage;
                                                                console.log('✅ x-effect: Sign canvas rendered page', currentPage);
                                                            });
                                                        } else {
                                                            console.log('⏭️ x-effect: Skip render, page', currentPage, 'already rendered');
                                                        }
                                                    }
                                                "></canvas>

                                            {{-- QR Overlay — hanya saat tipe gambar = coordinate --}}
                                            <template x-if="pdfLoaded && $wire.simImageType === 'coordinate'">
                                                <div x-data="qrPositioner({
                                                    originX: @entangle('simOriginX').live,
                                                    originY: @entangle('simOriginY').live,
                                                    width: @entangle('simWidth').live,
                                                    height: @entangle('simHeight').live,
                                                    pdfWidth: @entangle('pdfWidth').live,
                                                    pdfHeight: @entangle('pdfHeight').live,
                                                    qrDataUri: @js($qrDataUri),
                                                })" x-ref="qrBox"
                                                    class="absolute cursor-move border-2 border-dashed border-primary-500 bg-primary-500/10 group z-20 select-none"
                                                    :style="qrStyle()" @mousedown.prevent="startDrag($event)"
                                                    @touchstart.prevent="startDrag($event)">
                                                    <img :src="qrSrc" alt="QR"
                                                        class="w-full h-full object-contain pointer-events-none opacity-80"
                                                        x-show="qrSrc" />
                                                    {{-- Resize handle --}}
                                                    <div class="absolute -right-1.5 -bottom-1.5 w-4 h-4 bg-primary-500 rounded-sm cursor-nwse-resize opacity-0 group-hover:opacity-100 transition-opacity z-20"
                                                        @mousedown.stop.prevent="startResize($event)"
                                                        @touchstart.stop.prevent="startResize($event)">
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                        {{-- End Canvas Wrapper --}}
                                    </div>

                                    {{-- Prev/Next Navigation (show when PDF loaded & pages > 1) --}}
                                    <div x-show="pdfLoaded && window.currentPdfDocument && window.currentPdfDocument.numPages > 1"
                                        x-cloak
                                        class="sticky bottom-4 mx-auto w-64 flex items-center gap-2 bg-white/90 dark:bg-primary-dark-800/90 backdrop-blur-sm px-4 py-2 rounded-lg shadow-lg border border-zinc-300 dark:border-primary-dark-600 z-30">
                                        <x-atoms.button type="button"
                                            x-on:click="switchPage(Math.max(1, currentPage - 1), true)"
                                            x-bind:disabled="currentPage <= 1"
                                            class="px-3 py-1 text-sm font-medium rounded bg-primary-500 text-white hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                            ← Prev
                                        </x-atoms.button>
                                        <span
                                            class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300 min-w-[60px] text-center"
                                            x-text="currentPage + ' / ' + (window.currentPdfDocument ? window.currentPdfDocument.numPages : 0)"></span>
                                        <x-atoms.button type="button"
                                            x-on:click="switchPage(Math.min((window.currentPdfDocument ? window.currentPdfDocument.numPages : 1), currentPage + 1), true)"
                                            x-bind:disabled="window.currentPdfDocument && currentPage >= window.currentPdfDocument.numPages"
                                            class="px-3 py-1 text-sm font-medium rounded bg-primary-500 text-white hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                            Next →
                                        </x-atoms.button>
                                    </div>
                                </div>

                                @if ($simImageType === 'coordinate')
                                    <p class="mt-2 text-xs text-zinc-400">Drag QR code untuk mengatur posisi. Tarik
                                        handle pojok kanan bawah untuk resize.</p>
                                @else
                                    <p class="mt-2 text-xs text-zinc-400">Mode Tag — posisi ditentukan oleh koordinat di
                                        form.</p>
                                @endif
                            </div>
                        </div>
                    </x-organisms.card>
                @endif

                {{-- Preview for INVISIBLE mode --}}
                @if ($simTampilan === 'INVISIBLE')
                    <x-organisms.card wire:key="preview-invisible" x-show="pdfBlobUrl" x-cloak title="Preview PDF">
                        <div class="overflow-hidden border rounded-lg border-zinc-300 dark:border-primary-dark-600"
                            style="aspect-ratio: {{ $pdfWidth && $pdfHeight ? $pdfWidth / $pdfHeight : 210 / 297 }}">
                            <iframe :src="pdfBlobUrl" class="w-full h-full" frameborder="0"></iframe>
                        </div>
                    </x-organisms.card>
                @endif
            </div>

            {{-- Kolom Kanan: Request & Response --}}
            <div class="xl:col-span-1 space-y-6">
                {{-- Form Sign --}}
                <x-organisms.card title="Simulasi Tanda Tangan PDF">
                    <form wire:submit="simulateSign" class="space-y-5">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>NIK</flux:label>
                                <flux:input wire:model="simNik" placeholder="Masukkan NIK" />
                                @error('simNik')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </flux:field>

                            <flux:field>
                                <flux:label>Passphrase</flux:label>
                                <flux:input type="password" wire:model="simPassphrase"
                                    placeholder="Masukkan passphrase" />
                                @error('simPassphrase')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Tampilan</flux:label>
                                <flux:select wire:model.live="simTampilan">
                                    <flux:select.option value="VISIBLE">VISIBLE</flux:select.option>
                                    <flux:select.option value="INVISIBLE">INVISIBLE</flux:select.option>
                                </flux:select>
                            </flux:field>

                            @if ($simTampilan === 'VISIBLE')
                                <flux:field>
                                    <flux:label>Mode</flux:label>
                                    <div
                                        class="inline-flex mt-1 rounded-lg border border-zinc-200 dark:border-primary-dark-700 p-0.5 bg-zinc-100 dark:bg-primary-dark-800 w-fit">
                                        @foreach ([['coordinate', 'map-pin', 'Coordinate'], ['tag', 'hashtag', 'Tag']] as [$val, $icon, $label])
                                            <label class="cursor-pointer">
                                                <input type="radio" wire:model.live="simImageType"
                                                    value="{{ $val }}" class="sr-only peer" />
                                                <span
                                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-all select-none
                                                    peer-checked:bg-white dark:peer-checked:bg-primary-dark-700 peer-checked:shadow-sm peer-checked:text-primary-600 dark:peer-checked:text-primary-400 peer-checked:font-semibold
                                                    text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-300">
                                                    <flux:icon name="{{ $icon }}" class="w-4 h-4" />
                                                    {{ $label }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </flux:field>
                            @endif
                        </div>

                        @if ($simTampilan === 'VISIBLE')
                            <div
                                class="grid grid-cols-2 gap-5 sm:grid-cols-3 {{ $simImageType === 'tag' ? 'lg:grid-cols-3' : 'lg:grid-cols-5' }}">
                                @if ($simImageType === 'tag')
                                    <flux:field>
                                        <flux:label>Tag Koordinat</flux:label>
                                        <div class="flex gap-1.5 mt-1 flex-wrap">
                                            @foreach (['#', '...', '@', '~'] as $tag)
                                                <label class="cursor-pointer">
                                                    <input type="radio" wire:model.live="simTagKoordinat"
                                                        value="{{ $tag }}" class="sr-only peer" />
                                                    <span
                                                        class="inline-flex items-center justify-center px-3 py-1.5 rounded-md border text-sm font-mono font-semibold transition-colors
                                                        peer-checked:bg-primary-500 peer-checked:text-white peer-checked:border-primary-500
                                                        border-zinc-300 dark:border-primary-dark-600 text-zinc-700 dark:text-primary-dark-300
                                                        hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400">{{ $tag }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </flux:field>
                                @else
                                    <flux:field>
                                        <flux:label>Halaman</flux:label>
                                        <flux:input type="number" wire:model.live="simPage" min="1"
                                            max="{{ $pdfPageCount ?? 999 }}" />
                                    </flux:field>
                                    <flux:field>
                                        <flux:label>Origin X (pt)</flux:label>
                                        <flux:input type="number" wire:model.live="simOriginX" step="0.01" />
                                    </flux:field>
                                    <flux:field>
                                        <flux:label>Origin Y (pt)</flux:label>
                                        <flux:input type="number" wire:model.live="simOriginY" step="0.01" />
                                    </flux:field>
                                @endif
                                <flux:field>
                                    <flux:label>Width (pt)</flux:label>
                                    <flux:input type="number" wire:model.live="simWidth" step="0.01" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>Height (pt)</flux:label>
                                    <flux:input type="number" wire:model.live="simHeight" step="0.01" />
                                </flux:field>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Location</flux:label>
                                <flux:input wire:model="simLocation" placeholder="Contoh: Jakarta" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Reason</flux:label>
                                <flux:input wire:model="simReason" placeholder="Alasan tanda tangan" />
                            </flux:field>
                        </div>

                        <div class="flex justify-end">
                            <x-atoms.button type="submit" variant="primary" icon="pencil-square">
                                Kirim Simulasi Sign
                            </x-atoms.button>
                        </div>
                    </form>
                </x-organisms.card>

                {{-- Live Request Preview --}}
                <x-organisms.card :padding="false">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
                        <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Request Preview</h3>
                        <div class="flex items-center gap-2">
                            @if ($simRequest)
                                <flux:badge color="green" size="sm">Terkirim</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Live</flux:badge>
                            @endif
                        </div>
                    </div>
                    <x-atoms.code-block language="json" maxHeight="max-h-[50vh]">{{ json_encode($simRequest ?? $simPreviewRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                </x-organisms.card>

                {{-- Response Section --}}
                <x-organisms.card title="Response">
                    @if ($simResult)
                        @if ($simResult['success'])
                            <div class="flex flex-col items-center justify-center gap-3 py-6">
                                <div
                                    class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                    <flux:icon name="check"
                                        class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Sign berhasil</p>
                                <x-atoms.button size="sm" variant="primary" icon="eye"
                                    x-on:click="$flux.modal('sign-result').show()">
                                    Lihat Hasil
                                </x-atoms.button>
                            </div>
                        @else
                            <div class="mb-3">
                                <flux:badge color="red" size="sm">Error</flux:badge>
                            </div>
                            <x-atoms.code-block language="json" maxHeight="max-h-[40vh]">{{ json_encode($simResult['data'] ?? $simResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                        @endif
                    @else
                        <div class="flex items-center justify-center h-40">
                            <div class="text-center">
                                <flux:icon name="pencil-square"
                                    class="w-10 h-10 mx-auto mb-2 text-zinc-300 dark:text-primary-dark-500" />
                                <p class="text-sm text-zinc-400">Kirim simulasi untuk melihat response</p>
                            </div>
                        </div>
                    @endif
                </x-organisms.card>
            </div>

            {{-- ==================== TAB: SIMULASI SEAL ==================== --}}
        @else
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                {{-- Kolom Kiri: PDF Preview + QR Positioner --}}
                <div class="xl:col-span-2 space-y-6">
                    {{-- PDF Preview dengan QR Overlay --}}
                    @if ($sealTampilan === 'VISIBLE')
                        <x-organisms.card x-show="pdfBlobUrl" x-cloak x-init="lastRenderedSealPage = null">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-base font-semibold text-zinc-900 dark:text-primary-dark-100">Preview & Posisi QR</h3>
                                <div class="flex items-center gap-3 text-xs text-zinc-500 dark:text-primary-dark-400">
                                    @if ($pdfPageCount)
                                        <div>
                                            <span>Halaman: </span>
                                            <code
                                                class="px-1.5 py-0.5 rounded bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 font-semibold">
                                                {{ $sealPage }} / {{ $pdfPageCount }}
                                            </code>
                                        </div>
                                    @endif
                                    @if ($pdfWidth && $pdfHeight)
                                        <div>
                                            <span>Ukuran: </span>
                                            <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700">
                                                {{ number_format($pdfWidth, 2) }} × {{ number_format($pdfHeight, 2) }}
                                                pt
                                            </code>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Container PDF + QR (Parent Scope) --}}
                            <div class="relative">
                                {{-- PDF Preview Container --}}
                                <div class="w-full">
                                    <div class="relative overflow-hidden border rounded-lg border-zinc-300 dark:border-primary-dark-600 bg-zinc-100 dark:bg-primary-dark-900 w-full"
                                        :style="pdfLoaded && pdfDimensions ? 'aspect-ratio: ' + (pdfDimensions.width /
                                            pdfDimensions
                                            .height) : 'aspect-ratio: 210 / 297'">

                                        {{-- Canvas Wrapper - match canvas size exactly --}}
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <div class="relative" x-ref="canvasWrapperSeal">
                                                {{-- PDF Canvas Preview --}}
                                                <canvas x-ref="pdfCanvasSeal" x-show="pdfBlobUrl"
                                                    class="block pointer-events-none"
                                                    x-effect="
                                                    if (pdfLoaded && sealPage && sealPage > 0 && $refs.pdfCanvasSeal) {
                                                        if (lastRenderedSealPage !== sealPage) {
                                                            console.log('🔄 Rendering Seal page:', sealPage);
                                                            renderPdfToCanvas($refs.pdfCanvasSeal, sealPage, false).then(() => {
                                                                console.log('✅ Seal canvas rendered successfully');
                                                            });
                                                            lastRenderedSealPage = sealPage;
                                                        }
                                                    }
                                                "></canvas>

                                                {{-- QR Overlay — hanya saat tipe gambar = coordinate --}}
                                                <template x-if="pdfLoaded && $wire.sealImageType === 'coordinate'">
                                                    <div x-data="qrPositioner({
                                                        originX: @entangle('sealOriginX'),
                                                        originY: @entangle('sealOriginY'),
                                                        width: @entangle('sealWidth'),
                                                        height: @entangle('sealHeight'),
                                                        pdfWidth: @entangle('pdfWidth'),
                                                        pdfHeight: @entangle('pdfHeight'),
                                                        qrDataUri: @js($qrDataUri),
                                                    })" x-ref="qrBox"
                                                        class="absolute cursor-move border-2 border-dashed border-primary-500 bg-primary-500/10 group z-20 select-none"
                                                        :style="qrStyle()" @mousedown.prevent="startDrag($event)"
                                                        @touchstart.prevent="startDrag($event)">
                                                        <img :src="qrSrc" alt="QR"
                                                            class="w-full h-full object-contain pointer-events-none opacity-80"
                                                            x-show="qrSrc" />
                                                        {{-- Resize handle --}}
                                                        <div class="absolute -right-1.5 -bottom-1.5 w-4 h-4 bg-primary-500 rounded-sm cursor-nwse-resize opacity-0 group-hover:opacity-100 transition-opacity z-20"
                                                            @mousedown.stop.prevent="startResize($event)"
                                                            @touchstart.stop.prevent="startResize($event)">
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                            {{-- End Canvas Wrapper --}}
                                        </div>

                                        {{-- Prev/Next Navigation (show when PDF loaded & pages > 1) --}}
                                        <div x-show="pdfLoaded && window.currentPdfDocument && window.currentPdfDocument.numPages > 1"
                                            x-cloak
                                            class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex items-center gap-2 bg-white/90 dark:bg-primary-dark-800/90 backdrop-blur-sm px-4 py-2 rounded-lg shadow-lg border border-zinc-300 dark:border-primary-dark-600 z-30">
                                            <x-atoms.button type="button"
                                                x-on:click="switchPage(Math.max(1, sealPage - 1), false)"
                                                x-bind:disabled="sealPage <= 1"
                                                class="px-3 py-1 text-sm font-medium rounded bg-primary-500 text-white hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                                ← Prev
                                            </x-atoms.button>
                                            <span
                                                class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300 min-w-[60px] text-center"
                                                x-text="sealPage + ' / ' + (window.currentPdfDocument ? window.currentPdfDocument.numPages : 0)"></span>
                                            <x-atoms.button type="button"
                                                x-on:click="switchPage(Math.min((window.currentPdfDocument ? window.currentPdfDocument.numPages : 1), sealPage + 1), false)"
                                                x-bind:disabled="window.currentPdfDocument && sealPage >= window.currentPdfDocument.numPages"
                                                class="px-3 py-1 text-sm font-medium rounded bg-primary-500 text-white hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                                Next →
                                            </x-atoms.button>
                                        </div>
                                    </div>

                                    @if ($sealImageType === 'coordinate')
                                        <p class="mt-2 text-xs text-zinc-400">Drag QR code untuk mengatur posisi. Tarik
                                            handle pojok kanan bawah untuk resize.</p>
                                    @else
                                        <p class="mt-2 text-xs text-zinc-400">Mode Tag — posisi ditentukan oleh
                                            koordinat di form.</p>
                                    @endif
                                </div>
                            </div>
                        </x-organisms.card>
                    @else
                        <x-organisms.card x-show="pdfBlobUrl" x-cloak title="Preview PDF">
                            <div class="overflow-hidden border rounded-lg border-zinc-300 dark:border-primary-dark-600"
                                style="aspect-ratio: {{ $pdfWidth && $pdfHeight ? $pdfWidth / $pdfHeight : 210 / 297 }}">
                                <iframe :src="pdfBlobUrl" class="w-full h-full" frameborder="0"></iframe>
                            </div>
                        </x-organisms.card>
                    @endif

                    {{-- Form Seal --}}
                    <x-organisms.card title="Simulasi Seal PDF">
                        <form wire:submit="simulateSeal" class="space-y-5">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>ID Subscriber</flux:label>
                                    <flux:input wire:model="sealIdSubscriber" placeholder="UUID subscriber" />
                                    @error('sealIdSubscriber')
                                        <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                </flux:field>

                                <flux:field>
                                    <flux:label>TOTP</flux:label>
                                    <flux:input wire:model="sealTotp" placeholder="Kode TOTP" />
                                    @error('sealTotp')
                                        <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                </flux:field>
                            </div>

                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>Tampilan</flux:label>
                                    <flux:select wire:model.live="sealTampilan">
                                        <flux:select.option value="INVISIBLE">INVISIBLE</flux:select.option>
                                        <flux:select.option value="VISIBLE">VISIBLE</flux:select.option>
                                    </flux:select>
                                </flux:field>

                                @if ($sealTampilan === 'VISIBLE')
                                    <flux:field>
                                        <flux:label>Mode</flux:label>
                                        <div
                                            class="inline-flex mt-1 rounded-lg border border-zinc-200 dark:border-primary-dark-700 p-0.5 bg-zinc-100 dark:bg-primary-dark-800 w-fit">
                                            @foreach ([['coordinate', 'map-pin', 'Coordinate'], ['tag', 'hashtag', 'Tag']] as [$val, $icon, $label])
                                                <label class="cursor-pointer">
                                                    <input type="radio" wire:model.live="sealImageType"
                                                        value="{{ $val }}" class="sr-only peer" />
                                                    <span
                                                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-all select-none
                                                        peer-checked:bg-white dark:peer-checked:bg-primary-dark-700 peer-checked:shadow-sm peer-checked:text-primary-600 dark:peer-checked:text-primary-400 peer-checked:font-semibold
                                                        text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-300">
                                                        <flux:icon name="{{ $icon }}" class="w-4 h-4" />
                                                        {{ $label }}
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </flux:field>
                                @endif
                            </div>

                            @if ($sealTampilan === 'VISIBLE')
                                <div
                                    class="grid grid-cols-2 gap-5 sm:grid-cols-3 {{ $sealImageType === 'tag' ? 'lg:grid-cols-3' : 'lg:grid-cols-5' }}">
                                    @if ($sealImageType === 'tag')
                                        <flux:field>
                                            <flux:label>Tag Koordinat</flux:label>
                                            <div class="flex gap-1.5 mt-1 flex-wrap">
                                                @foreach (['#', '...', '@', '~'] as $tag)
                                                    <label class="cursor-pointer">
                                                        <input type="radio" wire:model.live="sealTagKoordinat"
                                                            value="{{ $tag }}" class="sr-only peer" />
                                                        <span
                                                            class="inline-flex items-center justify-center px-3 py-1.5 rounded-md border text-sm font-mono font-semibold transition-colors
                                                            peer-checked:bg-primary-500 peer-checked:text-white peer-checked:border-primary-500
                                                            border-zinc-300 dark:border-primary-dark-600 text-zinc-700 dark:text-primary-dark-300
                                                            hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400">{{ $tag }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </flux:field>
                                    @else
                                        <flux:field>
                                            <flux:label>Halaman</flux:label>
                                            <flux:input type="number" wire:model.live="sealPage" min="1"
                                                max="{{ $pdfPageCount ?? 999 }}" />
                                        </flux:field>
                                        <flux:field>
                                            <flux:label>Origin X (pt)</flux:label>
                                            <flux:input type="number" wire:model.live="sealOriginX"
                                                step="0.01" />
                                        </flux:field>
                                        <flux:field>
                                            <flux:label>Origin Y (pt)</flux:label>
                                            <flux:input type="number" wire:model.live="sealOriginY"
                                                step="0.01" />
                                        </flux:field>
                                    @endif
                                    <flux:field>
                                        <flux:label>Width (pt)</flux:label>
                                        <flux:input type="number" wire:model.live="sealWidth" step="0.01" />
                                    </flux:field>
                                    <flux:field>
                                        <flux:label>Height (pt)</flux:label>
                                        <flux:input type="number" wire:model.live="sealHeight" step="0.01" />
                                    </flux:field>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>Location</flux:label>
                                    <flux:input wire:model="sealLocation" placeholder="Contoh: Jakarta" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>Reason</flux:label>
                                    <flux:input wire:model="sealReason" placeholder="Alasan seal" />
                                </flux:field>
                            </div>

                            <div class="flex justify-end">
                                <x-atoms.button type="submit" variant="primary" icon="shield-check">
                                    Kirim Simulasi Seal
                                </x-atoms.button>
                            </div>
                        </form>
                    </x-organisms.card>
                </div>

                {{-- Kolom Kanan: Request & Response --}}
                <div class="xl:col-span-1 space-y-6">
                    {{-- Live Request Preview --}}
                    <x-organisms.card :padding="false">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
                            <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Request Preview</h3>
                            <div class="flex items-center gap-2">
                                @if ($sealRequest)
                                    <flux:badge color="green" size="sm">Terkirim</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Live</flux:badge>
                                @endif
                            </div>
                        </div>
                        <x-atoms.code-block language="json" maxHeight="max-h-[50vh]">{{ json_encode($sealRequest ?? $sealPreviewRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </x-organisms.card>

                    {{-- Response Section --}}
                    <x-organisms.card title="Response">
                        @if ($sealResult)
                            @if ($sealResult['success'])
                                <div class="flex flex-col items-center justify-center gap-3 py-6">
                                    <div
                                        class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                        <flux:icon name="check"
                                            class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                                    </div>
                                    <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Seal berhasil
                                    </p>
                                    <x-atoms.button size="sm" variant="primary" icon="eye"
                                        x-on:click="$flux.modal('seal-result').show()">
                                        Lihat Hasil
                                    </x-atoms.button>
                                </div>
                            @else
                                <div class="mb-3">
                                    <flux:badge color="red" size="sm">Error</flux:badge>
                                </div>
                                <x-atoms.code-block language="json" maxHeight="max-h-[40vh]">{{ json_encode($sealResult['data'] ?? $sealResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                            @endif
                        @else
                            <div class="flex items-center justify-center h-40">
                                <div class="text-center">
                                    <flux:icon name="shield-check"
                                        class="w-10 h-10 mx-auto mb-2 text-zinc-300 dark:text-primary-dark-500" />
                                    <p class="text-sm text-zinc-400">Kirim simulasi untuk melihat response</p>
                                </div>
                            </div>
                        @endif
                    </x-organisms.card>
                </div>
            </div>
    @endif

    {{-- ==================== MODAL: HASIL SIGN ==================== --}}
    @if ($simResult && $simResult['success'])
        <x-organisms.modal name="sign-result" maxWidth="6xl" title="">
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                            <flux:icon name="pencil-square" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-primary-dark-100">Hasil Simulasi
                                Sign
                            </h3>
                            @if (!empty($simResult['data']['time']))
                                <p class="text-xs text-zinc-400">{{ $simResult['data']['time'] }}</p>
                            @endif
                        </div>
                    </div>
                    <flux:badge color="green">Success</flux:badge>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                    {{-- PDF Preview --}}
                    <div class="lg:col-span-3">
                        <p class="text-xs font-medium text-zinc-500 dark:text-primary-dark-400 mb-1.5">PDF Hasil</p>
                        <div class="border border-zinc-200 dark:border-primary-dark-700 rounded-lg overflow-hidden bg-zinc-50 dark:bg-primary-dark-900"
                            style="height: 70vh">
                            <iframe :src="signResultBlobUrl" class="w-full h-full" frameborder="0"
                                x-show="signResultBlobUrl"></iframe>
                            <div x-show="!signResultBlobUrl"
                                class="w-full h-full flex items-center justify-center text-sm text-zinc-400">
                                PDF tidak tersedia
                            </div>
                        </div>
                    </div>

                    {{-- Response --}}
                    <div class="lg:col-span-2 flex flex-col gap-3">
                        <div>
                            <p class="text-xs font-medium text-zinc-500 dark:text-primary-dark-400 mb-1.5">Response</p>
                            <x-atoms.code-block language="json" maxHeight="max-h-[calc(70vh-60px)]">{{ json_encode(collect($simResult['data'] ?? [])->except('file')->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                        </div>
                        <x-atoms.button wire:click="downloadSimSign" variant="primary" icon="arrow-down-tray"
                            class="w-full">
                            Download PDF
                        </x-atoms.button>
                    </div>
                </div>
            </div>
        
    </x-organisms.modal>
    @endif

    {{-- ==================== MODAL: HASIL SEAL ==================== --}}
    @if ($sealResult && $sealResult['success'])
        <x-organisms.modal name="seal-result" maxWidth="6xl" title="">
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                            <flux:icon name="shield-check" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-primary-dark-100">Hasil Simulasi
                                Seal
                            </h3>
                            @if (!empty($sealResult['data']['time']))
                                <p class="text-xs text-zinc-400">{{ $sealResult['data']['time'] }}</p>
                            @endif
                        </div>
                    </div>
                    <flux:badge color="green">Success</flux:badge>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                    {{-- PDF Preview --}}
                    <div class="lg:col-span-3">
                        <p class="text-xs font-medium text-zinc-500 dark:text-primary-dark-400 mb-1.5">PDF Hasil</p>
                        <div class="border border-zinc-200 dark:border-primary-dark-700 rounded-lg overflow-hidden bg-zinc-50 dark:bg-primary-dark-900"
                            style="height: 70vh">
                            <iframe :src="sealResultBlobUrl" class="w-full h-full" frameborder="0"
                                x-show="sealResultBlobUrl"></iframe>
                            <div x-show="!sealResultBlobUrl"
                                class="w-full h-full flex items-center justify-center text-sm text-zinc-400">
                                PDF tidak tersedia
                            </div>
                        </div>
                    </div>

                    {{-- Response --}}
                    <div class="lg:col-span-2 flex flex-col gap-3">
                        <div>
                            <p class="text-xs font-medium text-zinc-500 dark:text-primary-dark-400 mb-1.5">Response</p>
                            <x-atoms.code-block language="json" maxHeight="max-h-[calc(70vh-60px)]">{{ json_encode(collect($sealResult['data'] ?? [])->except('file')->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                        </div>
                        <x-atoms.button wire:click="downloadSimSeal" variant="primary" icon="arrow-down-tray"
                            class="w-full">
                            Download PDF
                        </x-atoms.button>
                    </div>
                </div>
            </div>
        
    </x-organisms.modal>
    @endif
</div>

@push('scripts')
    @once
        <script>
            // Register Alpine component - execute immediately or wait for Alpine init
            function registerQrPositioner() {
                if (typeof Alpine === 'undefined') {
                    console.error('Alpine is not loaded yet');
                    return false;
                }

                Alpine.data('qrPositioner', (config) => ({
                    // Dimensi PDF dari Livewire (dalam points)
                    pdfWidth: config.pdfWidth,
                    pdfHeight: config.pdfHeight,

                    // Properties yang di-entangle dengan Livewire
                    originX: config.originX,
                    originY: config.originY,
                    width: config.width,
                    height: config.height,
                    qrSrc: config.qrDataUri || null,

                    // Working properties untuk drag/resize (local only, tidak sync ke Livewire)
                    workingOriginX: config.originX,
                    workingOriginY: config.originY,
                    workingWidth: config.width,
                    workingHeight: config.height,

                    dragging: false,
                    resizing: false,
                    dragOffsetX: 0,
                    dragOffsetY: 0,

                    init() {
                        console.log('🎯 QR Positioner Init:', {
                            pdfWidth: this.pdfWidth,
                            pdfHeight: this.pdfHeight,
                            width: this.width,
                            height: this.height,
                            originX: this.originX,
                            originY: this.originY,
                            paper: this.paper
                        });
                        console.log('🔧 qrPositioner initialized with:', {
                            originX: this.originX,
                            originY: this.originY,
                            width: this.width,
                            height: this.height,
                            pdfWidth: this.pdfWidth,
                            pdfHeight: this.pdfHeight
                        });

                        // Watch entangled properties dan sync ke working properties
                        this.$watch('originX', (value) => {
                            // console.log('📝 originX changed to:', value);
                            if (!this.dragging && !this.resizing) this.workingOriginX = value;
                        });
                        this.$watch('originY', (value) => {
                            // console.log('📝 originY changed to:', value);
                            if (!this.dragging && !this.resizing) this.workingOriginY = value;
                        });
                        this.$watch('width', (value) => {
                            // console.log('📝 width changed to:', value);
                            if (!this.dragging && !this.resizing) this.workingWidth = value;
                        });
                        this.$watch('height', (value) => {
                            // console.log('📝 height changed to:', value);
                            if (!this.dragging && !this.resizing) this.workingHeight = value;
                        });

                        // Watch PDF dimensions update
                        this.$watch('pdfWidth', (value) => {
                            // console.log('📐 pdfWidth changed to:', value);
                        });
                        this.$watch('pdfHeight', (value) => {
                            // console.log('📐 pdfHeight changed to:', value);
                        });
                    },

                    // Getter untuk paper dimensions (fallback ke A4 jika belum ada)
                    get paper() {
                        return {
                            w: this.pdfWidth || 595.28,
                            h: this.pdfHeight || 841.89
                        };
                    },

                    // Getter untuk aspect ratio container
                    getAspectRatio() {
                        if (this.pdfWidth && this.pdfHeight) {
                            return this.pdfWidth / this.pdfHeight;
                        }
                        return 210 / 297; // Default A4
                    },

                    getContainer() {
                        // Return canvas element sebagai reference untuk koordinat

                        // Strategy 0: Akses parent Alpine scope untuk ambil canvas ref
                        // Cek apakah ada parent Alpine component dengan pdfCanvas ref
                        let element = this.$el;
                        let canvas = null;

                        // Traverse up untuk find parent dengan $refs.pdfCanvas
                        while (element && !canvas) {
                            // Cek apakah element punya Alpine component dengan pdfCanvas
                            if (element._x_dataStack) {
                                for (let data of element._x_dataStack) {
                                    if (data.$refs && data.$refs.pdfCanvas) {
                                        canvas = data.$refs.pdfCanvas;
                                        console.log('✅ Canvas found via parent $refs');
                                        return canvas;
                                    }
                                }
                            }
                            element = element.parentElement;
                        }

                        // Strategy 1: Cari dari wrapper dengan querySelector
                        const wrapper = this.$el.parentElement;
                        if (!wrapper) {
                            console.warn('⚠️ No parent wrapper found');
                            return null;
                        }

                        canvas = wrapper.querySelector('canvas');
                        if (canvas) {
                            // console.log('✅ Canvas found via querySelector');
                            return canvas;
                        }

                        // Strategy 2: Traverse semua children untuk find canvas
                        const children = Array.from(wrapper.children);
                        canvas = children.find(child => child.tagName === 'CANVAS');
                        if (canvas) {
                            console.log('✅ Canvas found via children traverse');
                            return canvas;
                        }

                        // Strategy 3: Traverse siblings (skip comment nodes dari x-if)
                        let sibling = this.$el.previousSibling;
                        while (sibling) {
                            if (sibling.nodeType === 1 && sibling.tagName === 'CANVAS') {
                                console.log('✅ Canvas found via sibling traverse');
                                return sibling;
                            }
                            sibling = sibling.previousSibling;
                        }

                        // Strategy 4: Cari dari document dengan x-ref
                        // Ambil canvas dengan matching ref name (pdfCanvas atau pdfCanvasSeal)
                        const allCanvas = document.querySelectorAll('canvas[x-ref^="pdfCanvas"]');
                        if (allCanvas.length > 0) {
                            // Ambil canvas pertama yang visible
                            for (let c of allCanvas) {
                                if (c.offsetWidth > 0 && c.offsetHeight > 0) {
                                    console.log('✅ Canvas found via document query');
                                    return c;
                                }
                            }
                        }

                        // Fallback to wrapper if canvas still not found
                        console.warn('⚠️ Canvas not found after all strategies. Using wrapper.');
                        console.log('📋 Debug info:', {
                            wrapperTag: wrapper.tagName,
                            childrenCount: wrapper.children.length,
                            children: Array.from(wrapper.children).map(c => c.tagName),
                            hasDataStack: !!this.$el._x_dataStack
                        });
                        return wrapper;
                    },

                    // Canvas sudah di-render 1:1 dengan PDF (1 CSS pixel = 1 point)
                    // Konversi menjadi sederhana: pt = px
                    ptToPx(pt) {
                        return pt;
                    },

                    pxToPt(px) {
                        return px;
                    },

                    // Style untuk QR overlay (canvas 1:1 dengan PDF, jadi pt = px)
                    qrStyle() {
                        const container = this.getContainer();
                        if (!container) {
                            console.warn('qrPositioner: container not found');
                            return 'display:none';
                        }

                        // Canvas 1:1 dengan PDF, koordinat langsung dalam pixels
                        const pxW = this.ptToPx(this.workingWidth);
                        const pxH = this.ptToPx(this.workingHeight);
                        const pxLeft = this.ptToPx(this.workingOriginX);
                        const pxTop = this.ptToPx(this.workingOriginY);

                        // Safeguard: gunakan fallback value jika NaN
                        const safeW = isNaN(pxW) || pxW <= 0 ? 100 : pxW;
                        const safeH = isNaN(pxH) || pxH <= 0 ? 100 : pxH;
                        const safeLeft = isNaN(pxLeft) ? 0 : pxLeft;
                        const safeTop = isNaN(pxTop) ? 0 : pxTop;

                        return `left:${safeLeft}px;top:${safeTop}px;width:${safeW}px;height:${safeH}px;`;
                    },

                    getClientPos(e) {
                        if (e.touches && e.touches.length > 0) {
                            return {
                                x: e.touches[0].clientX,
                                y: e.touches[0].clientY
                            };
                        }
                        return {
                            x: e.clientX,
                            y: e.clientY
                        };
                    },

                    startDrag(e) {
                        if (this.resizing) return;
                        this.dragging = true;

                        const pos = this.getClientPos(e);
                        const container = this.getContainer();
                        const rect = container.getBoundingClientRect();

                        // Canvas 1:1 dengan PDF, jadi pt = px
                        const pxLeft = this.ptToPx(this.workingOriginX);
                        const pxTop = this.ptToPx(this.workingOriginY);
                        const pxSize = this.ptToPx(this.workingWidth);

                        this.dragOffsetX = pos.x - rect.left - pxLeft;
                        this.dragOffsetY = pos.y - rect.top - pxTop;

                        const onMove = (ev) => {
                            if (!this.dragging) return;
                            ev.preventDefault();

                            const movePos = this.getClientPos(ev);
                            // Batasi dalam area PDF (paper dimensions = canvas size)
                            const newPxLeft = Math.max(0, Math.min(
                                movePos.x - rect.left - this.dragOffsetX,
                                this.paper.w - pxSize
                            ));
                            const newPxTop = Math.max(0, Math.min(
                                movePos.y - rect.top - this.dragOffsetY,
                                this.paper.h - pxSize
                            ));

                            // Canvas 1:1 dengan PDF, konversi langsung
                            this.workingOriginX = Math.round(this.pxToPt(newPxLeft) * 100) / 100;
                            this.workingOriginY = Math.round(this.pxToPt(newPxTop) * 100) / 100;

                            // Sync to entangled properties LIVE
                            this.originX = this.workingOriginX;
                            this.originY = this.workingOriginY;
                        };

                        const onEnd = () => {
                            this.dragging = false;

                            // Final sync working values ke entangled properties
                            this.originX = this.workingOriginX;
                            this.originY = this.workingOriginY;

                            console.log('✅ Drag ended. Final position:', {
                                originX: this.originX,
                                originY: this.originY
                            });

                            document.removeEventListener('mousemove', onMove);
                            document.removeEventListener('mouseup', onEnd);
                            document.removeEventListener('touchmove', onMove);
                            document.removeEventListener('touchend', onEnd);
                        };

                        document.addEventListener('mousemove', onMove);
                        document.addEventListener('mouseup', onEnd);
                        document.addEventListener('touchmove', onMove, {
                            passive: false
                        });
                        document.addEventListener('touchend', onEnd);
                    },

                    startResize(e) {
                        this.resizing = true;

                        const pos = this.getClientPos(e);
                        // Canvas 1:1 dengan PDF, jadi pt = px
                        const startSize = this.ptToPx(this.workingWidth);
                        const startX = pos.x;
                        const startY = pos.y;

                        const minPx = 30;

                        const onMove = (ev) => {
                            if (!this.resizing) return;
                            ev.preventDefault();

                            const movePos = this.getClientPos(ev);
                            const dx = movePos.x - startX;
                            const dy = movePos.y - startY;

                            // Maintain 1:1 aspect ratio - gunakan nilai terbesar antara dx dan dy
                            const delta = Math.max(dx, dy);
                            const newSize = Math.max(minPx, startSize + delta);

                            // Batasi dalam area PDF (paper dimensions)
                            const pxLeft = this.ptToPx(this.workingOriginX);
                            const pxTop = this.ptToPx(this.workingOriginY);

                            const maxW = this.paper.w - pxLeft;
                            const maxH = this.paper.h - pxTop;
                            const maxSize = Math.min(maxW, maxH);

                            // Set width dan height dengan nilai yang sama (1:1 ratio)
                            const finalSize = Math.min(newSize, maxSize);
                            // Canvas 1:1 dengan PDF, konversi langsung
                            const ptSize = Math.round(this.pxToPt(finalSize) * 100) / 100;

                            // Update working properties (local for smooth visual)
                            this.workingWidth = ptSize;
                            this.workingHeight = ptSize;

                            // Sync to entangled properties LIVE
                            this.width = this.workingWidth;
                            this.height = this.workingHeight;
                        };

                        const onEnd = () => {
                            this.resizing = false;

                            // Final sync working values ke entangled properties
                            this.width = this.workingWidth;
                            this.height = this.workingHeight;

                            console.log('✅ Resize ended. Final size:', {
                                width: this.width,
                                height: this.height
                            });

                            document.removeEventListener('mousemove', onMove);
                            document.removeEventListener('mouseup', onEnd);
                            document.removeEventListener('touchmove', onMove);
                            document.removeEventListener('touchend', onEnd);
                        };

                        document.addEventListener('mousemove', onMove);
                        document.addEventListener('mouseup', onEnd);
                        document.addEventListener('touchmove', onMove, {
                            passive: false
                        });
                        document.addEventListener('touchend', onEnd);
                    },
                }));

                return true;
            }

            // Try to register immediately if Alpine already loaded
            if (typeof Alpine !== 'undefined') {
                registerQrPositioner();
            } else {
                // Wait for Alpine init event
                document.addEventListener('alpine:init', () => {
                    registerQrPositioner();
                });
            }
        </script>
    @endonce
@endpush
