<?php

namespace App\Providers;

use App\Importers\Filesystem;
use App\Importers\FilesystemInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ImportersFilesystemServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(FilesystemInterface::class, fn () => new Filesystem('imports'));
    }

    public function provides(): array
    {
        return [FilesystemInterface::class];
    }
}
