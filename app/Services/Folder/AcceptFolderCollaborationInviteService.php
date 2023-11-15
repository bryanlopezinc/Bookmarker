<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Cache\InviteTokensStore;
use App\Exceptions\HttpException;
use App\UAC;
use App\Repositories\Folder\FolderPermissionsRepository;
use Illuminate\Http\Request;
use App\Cache\InviteTokensStore as Payload;
use App\DataTransferObjects\FolderSettings;
use App\Exceptions\UserNotFoundException;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\User;
use App\Notifications\NewCollaboratorNotification as Notification;

final class AcceptFolderCollaborationInviteService
{
    public function __construct(
        private FolderPermissionsRepository $permissions,
        private InviteTokensStore $inviteTokensStore,
        private FetchFolderService $folderRepository
    ) {
    }

    public function fromRequest(Request $request): void
    {
        $token = $request->input('invite_hash');
        $payload = $this->inviteTokensStore->get($token);

        [$inviterId, $inviteeId, $folderId] = [
            $payload[Payload::INVITER_ID] ?? null,
            $payload[Payload::INVITEE_ID] ?? null,
            $payload[Payload::FOLDER_ID]  ?? null,
        ];

        $this->ensureInvitationTokenIsValid($payload);

        $this->ensureUsersStillExist($inviterId, $inviteeId);

        $folder = $this->folderRepository->find($folderId, ['id', 'user_id', 'settings']);

        $this->ensureInvitationHasNotBeenAccepted($inviteeId, $folderId);

        $this->permissions->create($inviteeId, $folder->id, $this->extractPermissions($payload));

        $this->inviteTokensStore->forget($token);

        $this->notifyFolderOwner($inviterId, $inviteeId, $folder);
    }

    private function ensureInvitationTokenIsValid(array $data): void
    {
        if (empty($data)) {
            throw HttpException::notFound(['message' => 'InvitationNotFoundOrExpired']);
        }
    }

    private function extractPermissions(array $payload): UAC
    {
        $assignedPermissions = $payload[Payload::PERMISSIONS];

        $defaultPermissions = new UAC([FolderPermission::VIEW_BOOKMARKS]);

        $permissionsSetByFolderOwner = new UAC($assignedPermissions);

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

    private function ensureUsersStillExist(int $inviterId, int $inviteeId): void
    {
        $users = User::query()
            ->select('id')
            ->whereIntegerInRaw('id', func_get_args())
            ->get();

        if ($users->count() !== 2) {
            throw new UserNotFoundException();
        }
    }

    private function ensureInvitationHasNotBeenAccepted(int $inviteeId, int $folderId): void
    {
        $access = $this->permissions->getUserAccessControls($inviteeId, $folderId);

        if ($access->isNotEmpty()) {
            throw HttpException::conflict(['message' => 'Invitation already accepted']);
        }
    }

    private function notifyFolderOwner(int $inviterId, int $inviteeId, Folder $folder): void
    {
        $wasInvitedByFolderOwner = $folder->user_id === $inviterId;

        $folderSettings = FolderSettings::fromQuery($folder->settings);

        if (
            ($folderSettings->notificationsAreDisabled() || $folderSettings->newCollaboratorNotificationIsDisabled()) ||
            (!$wasInvitedByFolderOwner && $folderSettings->onlyCollaboratorsInvitedByMeNotificationIsEnabled()) ||
            ($wasInvitedByFolderOwner && $folderSettings->onlyCollaboratorsInvitedByMeNotificationIsDisabled())
        ) {
            return;
        }

        (new User(['id' => $folder->user_id]))->notify(
            new Notification($inviteeId, $folder->id, $inviterId)
        );
    }
}
