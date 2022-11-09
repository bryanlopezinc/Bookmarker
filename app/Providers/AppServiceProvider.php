<?php

namespace App\Providers;

use App\Cache\User2FACodeRepository;
use App\Contracts\TwoFACodeGeneratorInterface;
use App\Repositories\OAuth\EnsureEmailHasBeenVerified;
use App\Repositories\OAuth\Verify2FACode;
use App\Utils\TwoFACodeGenerator;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        $this->app->bind(TwoFACodeGeneratorInterface::class, TwoFACodeGenerator::class);

        Relation::enforceMorphMap([
            'user' => \App\Models\User::class
        ]);
    }
}
