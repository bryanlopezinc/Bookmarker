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
use App\Enums\Permission;
use App\Exceptions\FolderCollaboratorsLimitExceededException;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Models\Folder;
use App\Models\FolderCollaboratorPermission;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Models\User;
use App\Notifications\NewCollaboratorNotification as Notification;

final class AcceptFolderCollaborationInviteService
{
    public function __construct(
        private FolderPermissionsRepository $permissions,
        private InviteTokensStore $inviteTokensStore,
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

        $this->ensureInviteTokenIsValid($payload);

        $this->ensureUsersStillExist($inviterId, $inviteeId);

        $folder = Folder::onlyAttributes(['id', 'user_id', 'settings', 'collaboratorsCount'])
            ->tap(new WhereFolderOwnerExists())
            ->addSelect([
                'collaborator' => FolderCollaboratorPermission::select('id')
                    ->where('folder_id', $folderId)
                    ->where('user_id', $inviteeId)
                    ->limit(1)
            ])
            ->find($folderId);

        FolderNotFoundException::throwIf(!$folder);

        FolderCollaboratorsLimitExceededException::throwIfExceeded($folder->collaboratorsCount);

        if ($folder->collaborator !== null) {
            return;
        }

        $this->permissions->create($inviteeId, $folder->id, $this->extractPermissions($payload));

        $this->notifyFolderOwner($inviterId, $inviteeId, $folder);
    }

    private function ensureInviteTokenIsValid(array $data): void
    {
        if (empty($data)) {
            throw HttpException::notFound(['message' => 'InvitationNotFoundOrExpired']);
        }
    }

    private function extractPermissions(array $payload): UAC
    {
        $defaultPermissions = new UAC([Permission::VIEW_BOOKMARKS]);

        $permissionsSetByFolderOwner = new UAC($payload[Payload::PERMISSIONS]);

        if ($permissionsSetByFolderOwner->isEmpty()) {
            return $defaultPermissions;
        }

        return $defaultPermissions
            ->toCollection()
            ->merge($permissionsSetByFolderOwner)
            ->unique()
            ->pipe(fn ($collection) => new UAC($collection->all()));
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
