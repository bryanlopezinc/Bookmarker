<?php

namespace App\Providers;

use App\Cache\Folder\FolderRepository as CacheRepository;
use App\Contracts\FolderRepositoryInterface;
use App\Repositories\Folder\CheckFolderBelongsToDeletedUser;
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
        $this->app->bind(FolderRepositoryInterface::class, function ($app) {
            return new CacheRepository(
                new CheckFolderBelongsToDeletedUser(new FolderRepository),
                $app['cache']->store()
            );
        });
    }

    public function provides()
    {
        return [
            FolderRepositoryInterface::class,
        ];
    }
}
