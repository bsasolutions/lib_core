<?php

declare(strict_types=1);

namespace Bsa\Core\Providers;

use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interfaces to implementations here;
    }

    public function boot(): void
    {
        // Register translations for the package
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'lib_core');

        // Opcional: Allow package translations to be published
        $this->publishes([
            __DIR__ . '/../../resources/lang' => resource_path('lang/vendor/lib_core'),
        ], 'lib_core-lang');
    }
}
