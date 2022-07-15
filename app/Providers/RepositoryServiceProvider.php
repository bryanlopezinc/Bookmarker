<?php

namespace App\Providers;

use App\Contracts\CreateBookmarkRepositoryInterface;
use App\Contracts\UpdateBookmarkRepositoryInterface;
use App\Repositories\CreateBookmarkRepository;
use App\Repositories\UpdateBookmarkRepository;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

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
    }

    public function provides()
    {
        return [
            CreateBookmarkRepositoryInterface::class,
            UpdateBookmarkRepositoryInterface::class
        ];
    }
}
