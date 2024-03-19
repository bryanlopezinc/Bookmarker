<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\Contracts\FolderRequestHandlerInterface;
use App\Models\Folder;
use App\Models\Scopes\IsMutedCollaboratorScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\Notification as NotificationSender;
use App\Models\User;
use App\Notifications\NewCollaboratorNotification as Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class SendNewCollaboratorNotification implements FolderRequestHandlerInterface, Scope, InvitationDataAwareInterface
{
    use Concerns\HasInvitationData;

    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $callback = new IsMutedCollaboratorScope($this->invitationData->inviterId);

        $builder->addSelect(['settings', 'user_id'])->tap($callback);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $inviter = new User($folder->inviter);

        $wasInvitedByFolderOwner = $folder->user_id === $inviter->id;

        $settings = $folder->settings;

        if ($folder->collaboratorIsMuted) {
            return;
        }

        if ($settings->notificationsAreDisabled || $settings->newCollaboratorNotificationIsDisabled) {
            return;
        }

        if ( ! $wasInvitedByFolderOwner && $settings->newCollaboratorNotificationMode->notifyWhenCollaboratorWasInvitedByMe()) {
            return;
        }

        if ($wasInvitedByFolderOwner && ! $settings->newCollaboratorNotificationMode->notifyWhenCollaboratorWasInvitedByMe()) {
            return;
        }

        NotificationSender::send(
            new User(['id' => $folder->user_id]),
            new Notification(new User($folder->invitee), $folder, $inviter)
        );
    }
}
