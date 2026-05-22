<?php

namespace App\Services;

use App\Helpers\ConfigurationHelper;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeService
{
    /**
     * Generate QR code dan kembalikan sebagai base64 PNG string
     *
     * @param array|null $imageOptions  ['base64' => string|null, 'size' => int|null]
     * @param array|null $qrCodeOptions ['color' => string|null, 'backgroundColor' => string|null, 'margin' => int|null, 'size' => int|null, 'level' => 'low'|'medium'|'quartile'|'high'|null]
     */
    public function generate(string $content, ?array $imageOptions, ?array $qrCodeOptions): string
    {
        $size = (int) ($qrCodeOptions['size'] ?? ConfigurationHelper::get('qrcode.size', 300));
        $margin = (int) ($qrCodeOptions['margin'] ?? ConfigurationHelper::get('qrcode.margin', 10));
        $fgHex = $qrCodeOptions['color'] ?? ConfigurationHelper::get('qrcode.foreground_color', '#000000');
        $bgHex = $qrCodeOptions['backgroundColor'] ?? ConfigurationHelper::get('qrcode.background_color', '#FFFFFF');
        $logoSize = (int) ($imageOptions['size'] ?? ConfigurationHelper::get('qrcode.logo_size', 60));

        $errorLevel = match ($qrCodeOptions['level'] ?? null) {
            'low' => ErrorCorrectionLevel::Low,
            'quartile' => ErrorCorrectionLevel::Quartile,
            'high' => ErrorCorrectionLevel::High,
            default => ErrorCorrectionLevel::Medium,
        };

        [$fr, $fg, $fb] = $this->parseHex($fgHex);
        [$br, $bg, $bb] = $this->parseHex($bgHex);

        [$logoPath, $isTempFile] = $this->resolveLogoPath($imageOptions['base64'] ?? null);

        try {
            $result = (new Builder(writer: new PngWriter()))->build(
                data: $content,
                size: $size,
                margin: $margin,
                errorCorrectionLevel: $errorLevel,
                foregroundColor: new Color($fr, $fg, $fb),
                backgroundColor: new Color($br, $bg, $bb),
                logoPath: $logoPath,
                logoResizeToWidth: $logoPath ? $logoSize : null,
                logoPunchoutBackground: $logoPath ? true : null,
            );

            return base64_encode($result->getString());
        } finally {
            if ($isTempFile && $logoPath && file_exists($logoPath)) {
                unlink($logoPath);
            }
        }
    }

    /** Tulis base64 logo ke file temp, atau gunakan path dari konfigurasi */
    private function resolveLogoPath(?string $base64): array
    {
        if ($base64) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'qr_logo_');
            file_put_contents($tmpFile, base64_decode($base64, strict: true));
            return [$tmpFile, true];
        }

        $logoEnabled = ConfigurationHelper::get('qrcode.logo_enabled');
        $logoPath = ConfigurationHelper::get('qrcode.logo_path');

        if ($logoEnabled && $logoPath && file_exists($logoPath)) {
            return [$logoPath, false];
        }

        return [null, false];
    }

    /** Parse hex color (#RRGGBB) ke array [R, G, B] */
    private function parseHex(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
