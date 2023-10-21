<?php

namespace App\Providers\Cache;

use App\Cache\EmailVerificationCodeRepository;
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
        $this->app->bind(EmailVerificationCodeRepository::class, function ($app) {
            return new EmailVerificationCodeRepository($app['cache']->store(), 300);
        });
    }

    public function provides(): array
    {
        return [EmailVerificationCodeRepository::class];
    }
}
