<?php

namespace App\Providers\Handlers;

use App\Models\User;
use App\Enums\Permission;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use App\Http\Handlers\Constraints\PermissionConstraint;
use App\DataTransferObjects\SendInviteRequestData;
use App\Enums\Feature;
use Illuminate\Contracts\Support\DeferrableProvider;
use App\Http\Handlers\Constraints\FeatureMustBeEnabledConstraint;
use App\Http\Handlers\SendInvite\Handler;

class SendInviteRequestServiceProvider extends ServiceProvider implements DeferrableProvider
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
            $this->registerConstraints($app);

            return new Handler();
        });
    }

    private function registerConstraints(Application $app): void
    {
        /** @var User */
        $authUser = $app->make('auth')->user();

        $app->bind(
            PermissionConstraint::class,
            fn () => new PermissionConstraint($authUser, Permission::INVITE_USER)
        );

        $app->bind(
            FeatureMustBeEnabledConstraint::class,
            fn () => new FeatureMustBeEnabledConstraint($authUser, Feature::SEND_INVITES)
        );
    }

    private function registerRequestDataBinding(): void
    {
        $this->app->bind(SendInviteRequestData::class, function (Application $app) {
            return SendInviteRequestData::fromRequest($app['request']);
        });
    }

    public function provides()
    {
        return [
            SendInviteRequestData::class,
            Handler::class
        ];
    }
}
