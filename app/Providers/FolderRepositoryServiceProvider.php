<?php

namespace App\Providers;

use App\Cache\Folder\FolderRepository as CacheRepository;
use App\Contracts\FolderRepositoryInterface;
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
        $this->app->instance(FolderRepositoryInterface::class, app(CacheRepository::class));
    }

    public function provides()
    {
        return [FolderRepositoryInterface::class];
    }
}
