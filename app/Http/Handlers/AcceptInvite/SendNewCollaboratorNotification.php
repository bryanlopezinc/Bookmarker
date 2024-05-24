<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\DataTransferObjects\FolderInviteData;
use App\Models\Folder;
use App\Models\Scopes\IsMutedCollaboratorScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\Notification as NotificationSender;
use App\Models\User;
use App\Notifications\NewCollaboratorNotification as Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Enums\NewCollaboratorNotificationMode as Mode;

final class SendNewCollaboratorNotification implements Scope
{
    public function __construct(
        private readonly FolderInviteData $invitationData,
        private readonly UserRepository $repository
    ) {
    }

    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $callback = new IsMutedCollaboratorScope($this->invitationData->inviterId);

        $builder->addSelect(['settings', 'user_id'])->tap($callback);
    }

    public function __invoke(Folder $folder): void
    {
        [$inviter, $invitee] = [$this->repository->inviter(), $this->repository->invitee()];

        $wasInvitedByFolderOwner = $folder->wasCreatedBy($inviter->id);

        $settings = $folder->settings;

        $mode = $settings->newCollaboratorNotificationMode()->value();

        if ($folder->collaboratorIsMuted) {
            return;
        }

        if ($settings->notifications()->isDisabled() || $settings->newCollaboratorNotification()->isDisabled()) {
            return;
        }

        if ( ! $wasInvitedByFolderOwner && $mode === Mode::INVITED_BY_ME) {
            return;
        }

        if ($wasInvitedByFolderOwner && $mode !== Mode::INVITED_BY_ME) {
            return;
        }

        $pendingDispatch = dispatch(static function () use ($folder, $inviter, $invitee) {
            NotificationSender::send(
                new User(['id' => $folder->user_id]),
                new Notification($invitee, $folder, $inviter)
            );
        });

        $pendingDispatch->afterResponse();
    }
}
