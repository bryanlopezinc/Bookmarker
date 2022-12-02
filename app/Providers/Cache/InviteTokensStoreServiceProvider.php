<?php

namespace App\Providers\Cache;

use App\Cache\InviteTokensStore;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class InviteTokensStoreServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(InviteTokensStore::class, function ($app) {
            return new InviteTokensStore($app['cache']->store(), 84600);
        });
    }

    public function provides(): array
    {
        return [InviteTokensStore::class];
    }
}
