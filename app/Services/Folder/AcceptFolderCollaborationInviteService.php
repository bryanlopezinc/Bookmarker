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
use App\ValueObjects\Uuid;

final class AcceptFolderCollaborationInviteService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private UserRepository $userRepository,
        private FolderPermissionsRepository $permissions,
        private InviteTokensStore $inviteTokensStore
    ) {
    }

    public function accept(Request $request): void
    {
        $invitationData = $this->inviteTokensStore->get(new Uuid($request->input('invite_hash')));

        $this->ensureInvitationTokenIsValid($invitationData);

        $this->ensureUsersStillExist($invitationData);

        $folder = $this->folderRepository->find(new ResourceID($invitationData[Payload::FOLDER_ID]), FolderAttributes::only('id'));
        $inviteeID = new UserID($invitationData[Payload::INVITEE_ID]);

        $this->ensureInvitationHasNotBeenAccepted($inviteeID, $folder);

        $this->permissions->create(
            $inviteeID,
            $folder->folderID,
            $this->extractPermissions($invitationData)
        );
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
        $permissionsCollaboratorWillHaveByDefault = UAC::fromArray(['read']);

        $permissionsSetByFolderOwner = UAC::fromUnSerialized($invitationData[Payload::PERMISSIONS]);

        if ($permissionsSetByFolderOwner->isEmpty()) {
            return $permissionsCollaboratorWillHaveByDefault;
        }

        return new UAC(
            collect($permissionsSetByFolderOwner->permissions)
                ->merge($permissionsCollaboratorWillHaveByDefault->permissions)
                ->unique()
                ->all()
        );
    }

    private function ensureUsersStillExist(array $payload): void
    {
        $result = $this->userRepository->findManyByIDs(
            IDs::fromNativeTypes([$payload[Payload::INVITER_ID], $payload[Payload::INVITEE_ID]]),
            \App\QueryColumns\UserAttributes::only('id')
        );

        if ($result->count() !== 2) {
            throw new UserNotFoundHttpException;
        }
    }

    private function ensureInvitationHasNotBeenAccepted(UserID $inviteeID, Folder $folder): void
    {
        $userHasAnyAccessToFolder = $this->permissions->getUserAccessControls($inviteeID, $folder->folderID)->isNotEmpty();

        if ($userHasAnyAccessToFolder) {
            throw HttpException::conflict([
                'message' => 'Invitation already accepted'
            ]);
        }
    }
}
