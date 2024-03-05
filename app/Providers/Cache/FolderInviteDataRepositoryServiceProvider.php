<?php

namespace App\Providers\Cache;

use App\Cache\FolderInviteDataRepository;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class FolderInviteDataRepositoryServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(FolderInviteDataRepository::class, function ($app) {
            return new FolderInviteDataRepository($app['cache']->store(), 84600);
        });
    }

    public function provides(): array
    {
        return [FolderInviteDataRepository::class];
    }
}