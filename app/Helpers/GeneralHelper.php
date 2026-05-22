<?php

namespace App\Helpers;

class GeneralHelper
{
    public static function getVersion()
    {
        $json = file_get_contents(base_path('version.json'), 'version.json');
        // Check if the file was read successfully
        if ($json === false) {
            die('Error reading the JSON file');
        }

        // Decode the JSON file
        $json_data = json_decode($json, true);

        // Check if the JSON was decoded successfully
        if ($json_data === null) {
            die('Error decoding the JSON file');
        }

        $versions = collect($json_data);
        $lastVersion = $versions->sortByDesc('version')
            ->first();

        return [
            'version' => 'Versi ' . $lastVersion['version'],
            'changeLog' => $lastVersion['changeLog'],
        ];
    }

    public static function numberFormat(float $numb, int $decimals = 0, string $decimal_separator = ',', string $thousand_separator = '.', bool $withCurrency = false, string $currency = 'Rp', string $currencyPosition = 'left'): string
    {
        $format = number_format($numb, $decimals, $decimal_separator, $thousand_separator);

        return $withCurrency ? ($currencyPosition == 'left' ? $currency . ' ' .  $format : $format . ' ' . $currency) : $format;
    }
}
