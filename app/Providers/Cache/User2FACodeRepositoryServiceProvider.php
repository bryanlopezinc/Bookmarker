<?php

declare(strict_types=1);

namespace App\Providers\Cache;

use App\Cache\User2FACodeRepository;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class User2FACodeRepositoryServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(User2FACodeRepository::class, function ($app) {
            return new User2FACodeRepository($app['cache']->store(), 600);
        });
    }

    public function provides(): array
    {
        return [User2FACodeRepository::class];
    }
}
