<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Cache\InviteTokensStore;
use App\Collections\ResourceIDsCollection as IDs;
use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Folder;
use App\Exceptions\HttpException;
use App\Exceptions\UserNotFoundHttpException;
use App\UAC;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\Repositories\UserRepository;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Http\Request;
use App\Cache\InviteTokensStore as Payload;
use App\Notifications\NewCollaboratorNotification as Notification;
use App\Repositories\NotificationRepository;
use App\ValueObjects\Uuid;

final class AcceptFolderCollaborationInviteService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private UserRepository $userRepository,
        private FolderPermissionsRepository $permissions,
        private InviteTokensStore $inviteTokensStore,
    ) {
    }

    public function accept(Request $request): void
    {
        $invitationData = $this->inviteTokensStore->get(new Uuid($request->input('invite_hash')));

        $this->ensureInvitationTokenIsValid($invitationData);

        $this->ensureUsersStillExist($invitationData);

        $folder = $this->folderRepository->find(new ResourceID($invitationData[Payload::FOLDER_ID]), FolderAttributes::only('id,user_id,settings'));
        $inviteeID = new UserID($invitationData[Payload::INVITEE_ID]);

        $this->ensureInvitationHasNotBeenAccepted($inviteeID, $folder);

        $this->permissions->create($inviteeID, $folder->folderID, $this->extractPermissions($invitationData));

        $this->notifyFolderOwner(new UserID($invitationData[Payload::INVITER_ID]), $inviteeID, $folder);
    }

    private function ensureInvitationTokenIsValid(array $data): void
    {
        if (empty($data)) {
            throw HttpException::notFound([
                'message' => 'Invitation not found or expired'
            ]);
        }
    }

    private function extractPermissions(array $invitationData): UAC
    {
        $defaultPermissions = UAC::fromArray(['read']);

        $permissionsSetByFolderOwner = UAC::fromUnSerialized($invitationData[Payload::PERMISSIONS]);

        if ($permissionsSetByFolderOwner->isEmpty()) {
            return $defaultPermissions;
        }

        return new UAC(
            collect($permissionsSetByFolderOwner->permissions)
                ->merge($defaultPermissions->permissions)
                ->unique()
                ->all()
        );
    }

    private function ensureUsersStillExist(array $payload): void
    {
        $users = $this->userRepository->findManyByIDs(
            IDs::fromNativeTypes([$payload[Payload::INVITER_ID], $payload[Payload::INVITEE_ID]]),
            \App\QueryColumns\UserAttributes::only('id')
        );

        if ($users->count() !== 2) {
            throw new UserNotFoundHttpException;
        }
    }

    private function ensureInvitationHasNotBeenAccepted(UserID $inviteeID, Folder $folder): void
    {
        $isAlreadyACollaborator = $this->permissions->getUserAccessControls($inviteeID, $folder->folderID)->isNotEmpty();

        if ($isAlreadyACollaborator) {
            throw HttpException::conflict([
                'message' => 'Invitation already accepted'
            ]);
        }
    }

    private function notifyFolderOwner(UserID $inviterID, UserID $inviteeID, Folder $folder): void
    {
        $wasInvitedByFolderOwner = $folder->ownerID->equals($inviterID);
        $wasNotInvitedByFolderOwner = !$wasInvitedByFolderOwner;
        $notification = new Notification($inviteeID, $folder->folderID, $inviterID);

        $shouldNotSendNotification = count(array_filter([
            ($folder->settings->notificationsAreDisabled() || $folder->settings->newCollaboratorNotificationIsDisabled()),
            ($wasNotInvitedByFolderOwner && $folder->settings->onlyCollaboratorsInvitedByMeNotificationIsEnabled()),
            ($wasInvitedByFolderOwner && $folder->settings->onlyCollaboratorsInvitedByMeNotificationIsDisabled())
        ])) > 0;

        if ($shouldNotSendNotification) {
            return;
        }

        (new NotificationRepository)->notify($folder->ownerID, $notification);
    }
}
