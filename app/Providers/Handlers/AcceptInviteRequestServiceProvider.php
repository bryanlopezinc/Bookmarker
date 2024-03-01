<?php

namespace App\Providers\Handlers;

use App\Http\Handlers\AcceptInvite\Handler;
use App\Contracts\AcceptFolderInviteRequestHandlerInterface as HandlerInterface;
use App\Enums\Feature;
use App\Http\Handlers\AcceptInvite\CheckForExpiredToken;
use App\Http\Handlers\Constraints\FeatureMustBeEnabledConstraint;
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
        $this->app->bind(HandlerInterface::class, function (Application $app) {
            $app->bind(
                FeatureMustBeEnabledConstraint::class,
                fn () => new FeatureMustBeEnabledConstraint(null, Feature::JOIN_FOLDER)
            );

            return new CheckForExpiredToken(new Handler());
        });
    }

    public function provides()
    {
        return [HandlerInterface::class];
    }
}
