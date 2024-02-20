<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use App\Actions\AddBookmarksToFolder as Handlers;
use App\Actions\AddBookmarksToFolder\RequestHandler;
use Illuminate\Contracts\Support\DeferrableProvider;

class AddBookmarksToFolderRequestHandlerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(RequestHandler::class, function (Application $app) {
            $handler = new RequestHandler();
            /** @var User */
            $authUser = $app->make('auth')->user();

            $handler->queue(new Handlers\FolderExistConstraint());
            $handler->queue(new Handlers\PermissionConstraint($authUser));
            $handler->queue(new Handlers\FeatureMustBeEnabledConstraint($authUser));
            $handler->queue(new Handlers\StorageSpaceConstraint());
            $handler->queue(new Handlers\UserOwnsBookmarksConstraint($authUser));
            $handler->queue(new Handlers\BookmarksExistConstraint());
            $handler->queue(new Handlers\CollaboratorCannotMarkBookmarksAsHiddenConstraint($authUser, $app->make('request')));
            $handler->queue(new Handlers\UniqueFolderBookmarkConstraint());
            $handler->queue(new Handlers\CreateNewFolderBookmarksHandler($app->make('request')));
            $handler->queue(new Handlers\NotificationHandler($authUser));
            $handler->queue(new Handlers\CheckBookmarksHealthHandler());

            return $handler;
        });
    }

    public function provides()
    {
        return [RequestHandler::class];
    }
}
