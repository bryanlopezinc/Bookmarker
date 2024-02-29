<?php

namespace App\Providers\Handlers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use App\DataTransferObjects\FetchFolderBookmarksRequestData as Data;
use App\Http\Handlers\FetchFolderBookmarks\GetFolderBookmarks;
use App\Http\Handlers\FetchFolderBookmarks\Handler;
use Illuminate\Contracts\Support\DeferrableProvider;

class FetchFolderBookmarksRequestServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerRequestDataBinding();

        $this->app->bind(Handler::class, function (Application $app) {
            return new Handler(new GetFolderBookmarks($app[Data::class]));
        });
    }

    private function registerRequestDataBinding(): void
    {
        $this->app->bind(Data::class, function (Application $app) {
            return Data::fromRequest($app['request']);
        });
    }

    public function provides()
    {
        return [
            Data::class,
            Handler::class
        ];
    }
}
