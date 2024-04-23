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

final class SendNewCollaboratorNotification implements Scope
{
    public function __construct(private readonly FolderInviteData $invitationData)
    {
    }

    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $callback = new IsMutedCollaboratorScope($this->invitationData->inviterId);

        $builder->addSelect(['settings', 'user_id'])->tap($callback);
    }

    public function __invoke(Folder $folder): void
    {
        [$inviter, $invitee] = [$folder->inviter, $folder->invitee];

        $wasInvitedByFolderOwner = $folder->user_id === $inviter['id'];

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

        $pendingDispatch = dispatch(static function () use ($folder, $inviter, $invitee) {
            NotificationSender::send(
                new User(['id' => $folder->user_id]),
                new Notification(new User($invitee), $folder, new User($inviter))
            );
        });

        $pendingDispatch->afterResponse();
    }
}
