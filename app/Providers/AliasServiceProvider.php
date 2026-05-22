<?php

namespace App\Providers;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class AliasServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $loader = AliasLoader::getInstance();
        $loader->alias('StatusHelper', \App\Helpers\StatusHelper::class);
        $loader->alias('GeneralHelper', \App\Helpers\GeneralHelper::class);
        $loader->alias('Cog', \App\Helpers\ConfigurationHelper::class);
        $loader->alias('Agent', \Jenssegers\Agent\Facades\Agent::class);
    }
}
