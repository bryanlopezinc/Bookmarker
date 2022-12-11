<?php

declare(strict_types=1);

namespace App\Providers\Cache;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\SplFileInfo;

final class RegisterProviders extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        collect(File::allFiles(__DIR__))
            ->map(fn (SplFileInfo $file) =>  __NAMESPACE__ . '\\' . $file->getFilenameWithoutExtension())
            ->filter(fn (string $provider) => $provider !== __CLASS__)
            ->each(fn (string $provider) => $this->app->register($provider));
    }
}
