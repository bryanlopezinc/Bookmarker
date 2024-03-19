<?php

declare(strict_types=1);

namespace App\Importing\Providers;

use App\Importing\Repositories\ImportStatRepository;
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
