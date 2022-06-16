<?php

namespace App\Providers;

use App\Contracts\BookmarksHealthRepositoryInterface;
use App\HealthChecker;
use App\Repositories\BookmarksHealthRepository;
use Tests\TestBookmarksHealthRepository;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Application;

class BookmarksHealthRepositoryServiceProvider extends ServiceProvider
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
        $concrete = new BookmarksHealthRepository;

        $this->app->bind(BookmarksHealthRepositoryInterface::class, fn () => $concrete);

        //Prevent faking Http response for every route that checks bookmarks health during tests.
        $this->app->addContextualBinding(
            HealthChecker::class,
            BookmarksHealthRepositoryInterface::class,
            fn (Application $app) => $app->environment('testing') ? new TestBookmarksHealthRepository : $concrete
        );
    }
}
