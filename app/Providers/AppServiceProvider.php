<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\User2FACodeRepository;
use App\Repositories\OAuth\EnsureEmailHasBeenVerified;
use App\Repositories\OAuth\Verify2FACode;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Http;
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
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->environment('testing')) {
            Http::preventStrayRequests();
        }

        $this->app->bind(UserRepository::class, function () {
            return new Verify2FACode(
                new EnsureEmailHasBeenVerified(
                    new UserRepository(app(Hasher::class))
                ),
                app(User2FACodeRepository::class)
            );
        });
    }
}
