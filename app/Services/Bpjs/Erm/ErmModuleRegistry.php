<?php

namespace App\Services\Bpjs\Erm;

use App\Helpers\ConfigurationHelper;

class ErmModuleRegistry
{
    const OPTIONAL_MODULES = ['procedure', 'medication', 'lab', 'radiologi', 'vital_sign'];

    public static function isEnabled(string $module): bool
    {
        return (bool) ConfigurationHelper::get("bpjs.erm.modules.{$module}", true);
    }

    public static function enabled(): array
    {
        return array_values(array_filter(self::OPTIONAL_MODULES, fn($m) => self::isEnabled($m)));
    }
}
