<?php

namespace App\Providers\Handlers;

use App\Http\Handlers\CreateFolder\CreateFolder;
use App\Http\Handlers\CreateFolder\HandlerInterface;
use App\Http\Handlers\CreateFolder\NormalizeFolderSettings;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;

class CreateFolderServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(HandlerInterface::class, function () {
            return new NormalizeFolderSettings(new CreateFolder());
        });
    }

    public function provides()
    {
        return [HandlerInterface::class];
    }
}
