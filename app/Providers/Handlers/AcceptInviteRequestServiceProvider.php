<?php

declare(strict_types=1);

namespace App\Providers\Handlers;

use App\Http\Handlers\AcceptInvite\Handler;
use App\Contracts\AcceptFolderInviteRequestHandlerInterface as HandlerInterface;
use App\Http\Handlers\AcceptInvite\CheckForExpiredToken;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;

class AcceptInviteRequestServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(HandlerInterface::class, function () {
            return new CheckForExpiredToken(new Handler());
        });
    }

    public function provides()
    {
        return [HandlerInterface::class];
    }
}
