<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\ResourceIDsCollection as IDs;
use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Folder;
use App\Exceptions\HttpException;
use App\Exceptions\UserNotFoundHttpException;
use App\FolderPermissions as Permissions;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\Repositories\UserRepository;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use App\Mail\FolderCollaborationInviteMail as Payload;

final class AcceptFolderCollaborationInviteService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private UserRepository $userRepository,
        private FolderPermissionsRepository $folderPermissionsRepository
    ) {
    }

    public function accept(Request $request): void
    {
        $decryted = Crypt::decrypt($request->input('invite_hash'));

        //Ensure Inviter and invitee still exist.
        $this->ensureUsersStillExist($decryted);

        //Ensure folder still exist.
        $folder = $this->folderRepository->find(new ResourceID($decryted[Payload::FOLDER_ID]), FolderAttributes::only('id'));

        $inviteeID = new UserID($decryted[Payload::INVITEE_ID]);

        $this->ensureInvitationHasNotBeenAccepted($inviteeID, $folder);

        $this->folderPermissionsRepository->create(
            $inviteeID,
            $folder->folderID,
            $this->extractPermissions($decryted)
        );
    }

    private function extractPermissions(array $decryted): Permissions
    {
        $permissionsCollaboratorWillHaveByDefault = Permissions::fromArray(['read']);

        $permissionsSetByfolderOwner = Permissions::fromUnSerialized($decryted[Payload::PERMISSIONS]);

        if (!$permissionsSetByfolderOwner->hasAnyPermission()) {
            return $permissionsCollaboratorWillHaveByDefault;
        }

        return new Permissions(
            array_merge($permissionsSetByfolderOwner->permissions, $permissionsCollaboratorWillHaveByDefault->permissions)
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
        $collaboratorExist = $this->folderPermissionsRepository->getUserPermissionsForFolder($inviteeID, $folder->folderID)->hasAnyPermission();

        if ($collaboratorExist) {
            throw HttpException::conflict([
                'message' => 'Invitation already accepted'
            ]);
        }
    }
}
