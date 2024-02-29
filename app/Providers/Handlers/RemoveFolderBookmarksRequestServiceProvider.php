<?php

namespace App\Providers\Handlers;

use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use App\Http\Handlers\Constraints\FeatureMustBeEnabledConstraint;
use App\Http\Handlers\Constraints\PermissionConstraint;
use App\DataTransferObjects\RemoveFolderBookmarksRequestData;
use App\Enums\Permission;
use App\Http\Handlers\RemoveFolderBookmarks\Handler;
use Illuminate\Contracts\Support\DeferrableProvider;

class RemoveFolderBookmarksRequestServiceProvider extends ServiceProvider implements DeferrableProvider
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
            fn () => new PermissionConstraint($authUser, Permission::DELETE_BOOKMARKS)
        );

        $app->bind(
            FeatureMustBeEnabledConstraint::class,
            fn () => new FeatureMustBeEnabledConstraint($authUser, Permission::DELETE_BOOKMARKS)
        );
    }

    private function registerRequestDataBinding(): void
    {
        $this->app->bind(RemoveFolderBookmarksRequestData::class, function (Application $app) {
            return RemoveFolderBookmarksRequestData::fromRequest($app['request']);
        });
    }

    public function provides()
    {
        return [
            RemoveFolderBookmarksRequestData::class,
            Handler::class,
        ];
    }
}
