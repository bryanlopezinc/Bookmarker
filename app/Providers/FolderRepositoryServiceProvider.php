<?php

namespace App\Providers;

use App\Cache\Folder\FolderRepository as CacheRepository;
use App\Contracts\FolderRepositoryInterface;
use App\Repositories\Folder\FolderRepository;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class FolderRepositoryServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $instance = new CacheRepository(new FolderRepository, $this->app['cache']->store(), 3600);

        $this->app->instance(FolderRepositoryInterface::class, $instance);
        $this->app->instance(CacheRepository::class, $instance);
    }

    public function provides()
    {
        return [
            FolderRepositoryInterface::class,
            CacheRepository::class
        ];
    }
}
