<?php

namespace App\Providers;

use App\Collections\BookmarksCollection;
use App\Contracts\HashedUrlInterface;
use App\Contracts\UrlHasherInterface;
use App\HashedUrl;
use App\Jobs\CheckBookmarksHealth;
use App\Observers\BookmarkObserver;
use App\TwoFA\Cache\VerificationCodesRepository;
use App\TwoFA\VerifyVerificationCode;
use App\UrlHasher;
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
        $this->app->bind(UserRepository::class, function ($app) {
            return new VerifyVerificationCode(
                new UserRepository(app(Hasher::class)),
                app(VerificationCodesRepository::class)
            );
        });

        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        }

        $this->app->terminating(function (BookmarkObserver $observer) {
            CheckBookmarksHealth::dispatch(new BookmarksCollection($observer->getRetrievedBookmarks()));
        });

        $this->bindUrlHasher();
    }

    private function bindUrlHasher(): void
    {
        $this->app->bind(UrlHasherInterface::class, fn () => new UrlHasher('xxh3'));
        $this->app->bind(HashedUrlInterface::class, fn () => new HashedUrl());
    }
}
