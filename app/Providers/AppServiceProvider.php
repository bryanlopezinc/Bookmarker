<?php

namespace App\Providers;

use App\Cache\User2FACodeRepository;
use App\Repositories\OAuth\EnsureEmailHasBeenVerified;
use App\Repositories\OAuth\Verify2FACode;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\UserRepository;

class AppServiceProvider extends ServiceProvider
{
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

        Model::preventLazyLoading($this->app->environment('local', 'testing'));

        $this->app->bind(UserRepository::class, function () {
            return new Verify2FACode(
                new EnsureEmailHasBeenVerified(
                    new UserRepository(app(Hasher::class))
                ),
                app(User2FACodeRepository::class)
            );
        });

        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        }

        Relation::enforceMorphMap([
            'user' => \App\Models\User::class
        ]);
    }
}
