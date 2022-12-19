<?php

namespace App\Providers\Cache;

use App\Cache\Folder\FolderRepository as CacheRepository;
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
        $this->app->instance(
            CacheRepository::class,
            new CacheRepository(new FolderRepository(), app('cache')->store(), 3600)
        );
    }

    public function provides()
    {
        return [CacheRepository::class];
    }
}
