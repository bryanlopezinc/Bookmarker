<?php

namespace App\Providers;

use App\Contracts\BookmarksHealthRepositoryInterface;
use App\Contracts\CreateBookmarkRepositoryInterface;
use App\Contracts\UpdateBookmarkRepositoryInterface;
use App\HealthChecker;
use App\Repositories\BookmarksHealthRepository;
use App\Repositories\CreateBookmarkRepository;
use App\Repositories\UpdateBookmarkRepository;
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
        $this->app->bind(CreateBookmarkRepositoryInterface::class, fn () => app(CreateBookmarkRepository::class));
        $this->app->bind(UpdateBookmarkRepositoryInterface::class, fn () => app(UpdateBookmarkRepository::class));

        $this->bindBookmarksHealth();
    }

    private function bindBookmarksHealth(): void
    {
        $concrete = new BookmarksHealthRepository;

        $this->app->bind(BookmarksHealthRepositoryInterface::class, fn () => $concrete);

        //Prevent faking Http response for every route that checks bookmarks health during tests.
        $this->app->addContextualBinding(
            HealthChecker::class,
            BookmarksHealthRepositoryInterface::class,
            fn (Application $app) => $app->environment('testing') ? new TestBookmarksHealthRepository($concrete) : $concrete
        );
    }

    public function provides()
    {
        return [
            CreateBookmarkRepositoryInterface::class,
            UpdateBookmarkRepositoryInterface::class,
            BookmarksHealthRepositoryInterface::class,
            HealthChecker::class
        ];
    }
}
