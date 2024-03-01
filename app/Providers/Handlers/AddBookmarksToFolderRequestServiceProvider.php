<?php

namespace App\Providers\Handlers;

use App\Http\Handlers\Constraints\FeatureMustBeEnabledConstraint;
use App\Http\Handlers\Constraints\PermissionConstraint;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use App\DataTransferObjects\AddBookmarksToFolderRequestData;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\AddBookmarksToFolder\Handler;
use Illuminate\Contracts\Support\DeferrableProvider;

class AddBookmarksToFolderRequestServiceProvider extends ServiceProvider implements DeferrableProvider
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
            fn () => new PermissionConstraint($authUser, Permission::ADD_BOOKMARKS)
        );

        $app->bind(
            FeatureMustBeEnabledConstraint::class,
            fn () => new FeatureMustBeEnabledConstraint($authUser, Feature::ADD_BOOKMARKS)
        );
    }

    private function registerRequestDataBinding(): void
    {
        $this->app->bind(AddBookmarksToFolderRequestData::class, function (Application $app) {
            return AddBookmarksToFolderRequestData::fromRequest($app['request']);
        });
    }

    public function provides()
    {
        return [
            Handler::class
        ];
    }
}
