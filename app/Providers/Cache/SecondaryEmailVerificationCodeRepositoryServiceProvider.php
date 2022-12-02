<?php

namespace App\Providers\Cache;

use App\Cache\SecondaryEmailVerificationCodeRepository;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class SecondaryEmailVerificationCodeRepositoryServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(SecondaryEmailVerificationCodeRepository::class, function ($app) {
            return new SecondaryEmailVerificationCodeRepository($app['cache']->store(), 300);
        });
    }

    public function provides(): array
    {
        return [SecondaryEmailVerificationCodeRepository::class];
    }
}
