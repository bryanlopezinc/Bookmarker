<?php

namespace App\Providers;

use App\Actions\AcceptFolderInvite\AcceptFolderInviteRequestHandler;
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
        $this->app->bind(AcceptFolderInviteRequestHandler::class, function () {
            $handler = new AcceptFolderInviteRequestHandler();

            $handler->queue(new Handlers\FolderExistsConstraint());
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
        return [AcceptFolderInviteRequestHandler::class];
    }
}
