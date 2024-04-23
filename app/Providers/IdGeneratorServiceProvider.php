<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\IdGeneratorInterface;
use App\NanoIdGenerator;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class IdGeneratorServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(IdGeneratorInterface::class, fn () => new NanoIdGenerator());
    }

    public function provides()
    {
        return [IdGeneratorInterface::class];
    }
}
