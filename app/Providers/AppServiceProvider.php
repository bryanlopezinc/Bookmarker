<?php

namespace App\Providers;

use App\TwoFA\Cache\VerificationCodesRepository;
use App\TwoFA\VerifyVerificationCode;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\UserRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(UserRepository::class, function ($app) {
            return new VerifyVerificationCode(
                new UserRepository(app(Hasher::class)), app(VerificationCodesRepository::class)
            );
        });

        if ($this->app->environment('local', 'testing')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        }
    }
}
