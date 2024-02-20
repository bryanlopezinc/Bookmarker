<?php

namespace App\Providers;

use App\Actions\AcceptFolderInvite\RequestHandler;
use App\Actions\AcceptFolderInvite as Handlers;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class AcceptFolderInviteRequestHandlerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(RequestHandler::class, function () {
            $handler = new RequestHandler();

            $handler->queue(new Handlers\FolderExistConstraint());
            $handler->queue(new Handlers\UniqueCollaboratorsConstraint());
            $handler->queue(new Handlers\InviterAndInviteeExistsConstraint());
            $handler->queue(new Handlers\VisibilityConstraint());
            $handler->queue(new Handlers\CollaboratorsLimitConstraint());
            $handler->queue(new Handlers\CreateNewCollaboratorHandler());
            $handler->queue(new Handlers\NotificationHandler());

            return $handler;
        });
    }

    public function provides()
    {
        return [RequestHandler::class];
    }
}
