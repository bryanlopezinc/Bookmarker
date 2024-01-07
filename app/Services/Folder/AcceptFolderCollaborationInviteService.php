<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Cache\InviteTokensStore;
use App\Exceptions\HttpException;
use App\UAC;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use Illuminate\Http\Request;
use App\Exceptions\FolderCollaboratorsLimitExceededException;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Models\Folder;
use App\Models\Scopes\IsMutedCollaboratorScope;
use App\Models\Scopes\UserIsACollaboratorScope;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Models\User;
use App\Notifications\NewCollaboratorNotification as Notification;
use App\Repositories\Folder\CollaboratorRepository;
use Illuminate\Support\Facades\Notification as NotificationSender;

final class AcceptFolderCollaborationInviteService
{
    public function __construct(
        private CollaboratorPermissionsRepository $permissions,
        private InviteTokensStore $inviteTokensStore,
        private CollaboratorRepository $collaboratorRepository
    ) {
    }

    public function fromRequest(Request $request): void
    {
        $token = $request->input('invite_hash');

        $payload = $this->inviteTokensStore->get($token);

        if (empty($payload)) {
            throw HttpException::notFound(['message' => 'InvitationNotFoundOrExpired']);
        }

        [$inviterId, $inviteeId, $folderId] = [
            $payload['inviterId'],
            $payload['inviteeId'],
            $payload['folderId'],
        ];

        $this->ensureUsersStillExist($inviterId, $inviteeId);

        /** @var Folder|null */
        $folder = Folder::onlyAttributes(['id', 'user_id', 'settings', 'collaboratorsCount', 'visibility'])
            ->tap(new WhereFolderOwnerExists())
            ->tap(new IsMutedCollaboratorScope($inviterId))
            ->tap(new UserIsACollaboratorScope($inviteeId, 'inviteeIsACollaborator'))
            ->find($folderId);

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        FolderCollaboratorsLimitExceededException::throwIfExceeded($folder->collaboratorsCount);

        if ($folder->visibility->isPrivate()) {
            throw HttpException::forbidden(['message' => 'PrivateFolder']);
        }

        if ($folder->visibility->isPasswordProtected()) {
            throw HttpException::forbidden(['message' => 'FolderIsPasswordProtected']);
        }

        if ($folder->inviteeIsACollaborator) {
            throw HttpException::conflict(['message' => 'InvitationAlreadyAccepted']);
        }

        $permissions = $this->extractPermissions($payload);

        $this->collaboratorRepository->create($folder->id, $inviteeId, $inviterId);

        if ($permissions->isNotEmpty()) {
            $this->permissions->create($inviteeId, $folder->id, $permissions);
        }

        $this->notifyFolderOwner($inviterId, $inviteeId, $folder);
    }

    private function extractPermissions(array $payload): UAC
    {
        $permissionsSetByFolderOwner = new UAC($payload['permissions']);

        if ($permissionsSetByFolderOwner->isEmpty()) {
            return new UAC([]);
        }

        return $permissionsSetByFolderOwner;
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

        $settings = $folder->settings;

        if ($settings->notificationsAreDisabled() || $settings->newCollaboratorNotificationIsDisabled()) {
            return;
        }

        if (!$wasInvitedByFolderOwner && $settings->onlyCollaboratorsInvitedByMeNotificationIsEnabled()) {
            return;
        }

        if ($wasInvitedByFolderOwner && $settings->onlyCollaboratorsInvitedByMeNotificationIsDisabled()) {
            return;
        }

        if ($folder->collaboratorIsMuted) {
            return;
        }

        NotificationSender::send(
            new User(['id' => $folder->user_id]),
            new Notification($inviteeId, $folder->id, $inviterId)
        );
    }
}
