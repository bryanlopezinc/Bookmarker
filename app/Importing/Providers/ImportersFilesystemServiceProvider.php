<?php

declare(strict_types=1);

namespace App\Importing\Providers;

use App\Importing\Filesystem;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\Storage;
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
        $this->app->bind(Filesystem::class, fn () => new Filesystem(Storage::disk('imports')));
    }

    public function provides(): array
    {
        return [Filesystem::class];
    }
}
