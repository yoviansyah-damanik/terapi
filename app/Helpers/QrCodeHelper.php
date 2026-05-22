<?php

namespace App\Helpers;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Illuminate\Support\Facades\Storage;

class QrCodeHelper
{
    /**
     * Generate QR code berdasarkan konfigurasi di database.
     *
     * Parameter $options memungkinkan override semua setting QR tanpa mengubah config global:
     *   error_correction  — L|M|Q|H
     *   size              — int px
     *   margin            — int px
     *   foreground_color  — hex string (#RRGGBB)
     *   background_color  — hex string (#RRGGBB)
     *   round_block_mode  — margin|shrink|none
     *   logo_enabled      — bool
     *   logo_path         — string (storage disk public path)
     *   logo_size         — int px
     */
    public static function generate(string $data, string $format = 'png', array $options = []): ResultInterface
    {
        $ecKey = $options['error_correction'] ?? ConfigurationHelper::get('qrcode.error_correction', 'M');
        $errorLevel = match ($ecKey) {
            'L'     => ErrorCorrectionLevel::Low,
            'Q'     => ErrorCorrectionLevel::Quartile,
            'H'     => ErrorCorrectionLevel::High,
            default => ErrorCorrectionLevel::Medium,
        };

        $size   = (int) ($options['size']   ?? ConfigurationHelper::get('qrcode.size',   '300'));
        $margin = (int) ($options['margin'] ?? ConfigurationHelper::get('qrcode.margin', '10'));

        $fgColor = self::hexToColor($options['foreground_color'] ?? ConfigurationHelper::get('qrcode.foreground_color', '#000000'));
        $bgColor = self::hexToColor($options['background_color'] ?? ConfigurationHelper::get('qrcode.background_color', '#FFFFFF'));

        $roundMode = match ($options['round_block_mode'] ?? ConfigurationHelper::get('qrcode.round_block_mode', 'margin')) {
            'shrink' => RoundBlockSizeMode::Shrink,
            'none'   => RoundBlockSizeMode::None,
            default  => RoundBlockSizeMode::Margin,
        };

        $writer = $format === 'svg' ? new SvgWriter() : new PngWriter();

        $logoPath         = '';
        $logoResizeToWidth = null;

        $logoEnabled = array_key_exists('logo_enabled', $options)
            ? (bool) $options['logo_enabled']
            : ConfigurationHelper::get('qrcode.logo_enabled', '0') === '1';

        // logo_absolute_path: jalur absolut (misal dari temp upload), bypass storage check
        if ($logoEnabled && isset($options['logo_absolute_path']) && file_exists($options['logo_absolute_path'])) {
            $logoPath          = $options['logo_absolute_path'];
            $logoResizeToWidth = (int) ($options['logo_size'] ?? ConfigurationHelper::get('qrcode.logo_size', '60'));
        } else {
            $configLogoPath = array_key_exists('logo_path', $options)
                ? $options['logo_path']
                : ConfigurationHelper::get('qrcode.logo_path');

            if ($logoEnabled && $configLogoPath && Storage::disk('public')->exists($configLogoPath)) {
                $logoPath          = Storage::disk('public')->path($configLogoPath);
                $logoResizeToWidth = (int) ($options['logo_size'] ?? ConfigurationHelper::get('qrcode.logo_size', '60'));
            }
        }

        $builder = new Builder(
            writer:               $writer,
            data:                 $data,
            encoding:             new Encoding('UTF-8'),
            errorCorrectionLevel: $errorLevel,
            size:                 $size,
            margin:               $margin,
            roundBlockSizeMode:   $roundMode,
            foregroundColor:      $fgColor,
            backgroundColor:      $bgColor,
            logoPath:             $logoPath,
            logoResizeToWidth:    $logoResizeToWidth,
        );

        return $builder->build();
    }

    private static function hexToColor(string $hex): Color
    {
        $hex = ltrim($hex, '#');
        $r   = (int) hexdec(substr($hex, 0, 2));
        $g   = (int) hexdec(substr($hex, 2, 2));
        $b   = (int) hexdec(substr($hex, 4, 2));

        return new Color($r, $g, $b);
    }
}
