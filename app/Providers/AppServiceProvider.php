<?php

namespace App\Providers;

use App\Collections\BookmarksCollection;
use App\Jobs\CheckBookmarksHealth;
use App\Observers\BookmarkObserver;
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
    }
}
