<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    public static function dateFormat($date, string $dateFormat = 'd/m/Y', bool $isTranslated = false, string $translatedFormat = 'd F Y')
    {
        if ($isTranslated)
            return Carbon::parse($date)->translatedFormat($translatedFormat);

        return Carbon::parse($date)->format($dateFormat);
    }

    public static function getAge($date, $withMonth = false, $withDay = false)
    {
        $format = '%y Tahun';

        if ($withMonth)
            $format .= ' %m Bulan';

        if ($withDay)
            $format .= ' %m Hari';

        return Carbon::parse($date)->diff(\Carbon\Carbon::now())->format($format);
    }

    public static function getDiffInDays($date, $dateDiff = null)
    {
        // ddd($date, $dateDiff, Carbon::createFromFormat('Y-m-d', $date)->startOfDay());
        if (!is_null($dateDiff)) {
            return Carbon::parse($date)->diffInDays(Carbon::parse($dateDiff)->addDays(1));
        }

        return Carbon::createFromFormat('Y-m-d', $date)->startOfDay()->diffInDays(Carbon::now());
    }
}
