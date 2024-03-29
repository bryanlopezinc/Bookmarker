<?php

namespace App\Providers;

use App\Contracts\BookmarksHealthRepositoryInterface;
use App\HealthChecker;
use App\Repositories\BookmarksHealthRepository;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Application;
use Tests\TestBookmarksHealthRepository;

class RepositoryServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bindBookmarksHealth();
    }

    private function bindBookmarksHealth(): void
    {
        $concrete = new BookmarksHealthRepository();

        $this->app->bind(BookmarksHealthRepositoryInterface::class, fn () => $concrete);

        //Prevent faking Http response for every route that checks bookmarks health during tests.
        $this->app->addContextualBinding(
            HealthChecker::class,
            BookmarksHealthRepositoryInterface::class,
            function (Application $app) use ($concrete) {
                return $app->environment('testing') ? new TestBookmarksHealthRepository($concrete) : $concrete;
            }
        );
    }

    public function provides()
    {
        return [
            BookmarksHealthRepositoryInterface::class,
            HealthChecker::class
        ];
    }
}
