<?php

namespace App\Providers;

use App\Collections\BookmarksCollection;
use App\Contracts\HashedUrlInterface;
use App\Contracts\UrlHasherInterface;
use App\HashedUrl;
use App\Jobs\CheckBookmarksHealth;
use App\Observers\BookmarkObserver;
use App\Cache\User2FACodeRepository;
use App\Contracts\TwoFACodeGeneratorInterface;
use App\Repositories\OAuth\EnsureEmailHasBeenVerified;
use App\Repositories\OAuth\Verify2FACode;
use App\Utils\UrlHasher;
use App\Utils\TwoFACodeGenerator;
use Illuminate\Contracts\Hashing\Hasher;
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

        $this->app->terminating(function (BookmarkObserver $observer) {
            CheckBookmarksHealth::dispatch(new BookmarksCollection($observer->getRetrievedBookmarks()));
        });

        $this->app->bind(TwoFACodeGeneratorInterface::class, TwoFACodeGenerator::class);

        $this->bindUrlHasher();
    }

    private function bindUrlHasher(): void
    {
        $this->app->bind(UrlHasherInterface::class, fn () => new UrlHasher('xxh3'));
        $this->app->bind(HashedUrlInterface::class, fn () => new HashedUrl());
    }
}
