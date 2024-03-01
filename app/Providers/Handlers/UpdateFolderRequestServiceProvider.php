<?php

namespace App\Providers\Handlers;

use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use App\Http\Handlers\Constraints\FeatureMustBeEnabledConstraint;
use App\Http\Handlers\Constraints\PermissionConstraint;
use App\Http\Handlers\UpdateFolder\SendFolderUpdatedNotification;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\UpdateFolder\Handler;
use App\Http\Handlers\UpdateFolder\UpdateFolder;
use Illuminate\Contracts\Support\DeferrableProvider;

class UpdateFolderRequestServiceProvider extends ServiceProvider implements DeferrableProvider
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
            fn () => new PermissionConstraint($authUser, Permission::UPDATE_FOLDER)
        );

        $app->bind(
            FeatureMustBeEnabledConstraint::class,
            fn () => new FeatureMustBeEnabledConstraint($authUser, Feature::UPDATE_FOLDER)
        );

        $app->bind(UpdateFolder::class, function () use ($app) {
            return new UpdateFolder($app[UpdateFolderRequestData::class], $app[SendFolderUpdatedNotification::class]);
        });
    }

    private function registerRequestDataBinding(): void
    {
        $this->app->bind(UpdateFolderRequestData::class, function (Application $app) {
            return UpdateFolderRequestData::fromRequest($app['request']);
        });
    }


    public function provides()
    {
        return [
            UpdateFolderRequestData::class,
            Handler::class,
        ];
    }
}
