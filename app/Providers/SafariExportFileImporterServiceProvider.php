<?php

namespace App\Providers;

use App\Importers\Safari\DOMParser;
use App\Importers\Safari\DOMParserInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class SafariExportFileImporterServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(DOMParserInterface::class, fn () => new DOMParser);
    }

    public function provides(): array
    {
        return [DOMParserInterface::class];
    }
}
