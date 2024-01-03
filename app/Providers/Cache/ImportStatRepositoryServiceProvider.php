<?php

namespace App\Providers\Cache;

use App\Cache\ImportStatRepository;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ImportStatRepositoryServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(ImportStatRepository::class, function ($app) {
            return new ImportStatRepository($app['cache']->store(), 84600);
        });
    }

    public function provides(): array
    {
        return [ImportStatRepository::class];
    }
}
