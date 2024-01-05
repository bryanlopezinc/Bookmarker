<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Cache\InviteTokensStore;
use App\Enums\Permission;
use App\Exceptions\FolderActionDisabledException;
use App\Exceptions\FolderNotFoundException;
use App\Models\{BannedCollaborator, Folder, FolderCollaborator, User};
use App\Exceptions\HttpException;
use App\Exceptions\FolderCollaboratorsLimitExceededException;
use App\Exceptions\PermissionDeniedException;
use App\Exceptions\UserNotFoundException;
use App\Http\Requests\SendFolderCollaborationInviteRequest as Request;
use App\Mail\FolderCollaborationInviteMail as InvitationMail;
use App\Models\Scopes\DisabledActionScope;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\UAC;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\{RateLimiter, Mail};
use Illuminate\Support\Str;

final class SendFolderCollaborationInviteService
{
    public function __construct(
        private CollaboratorPermissionsRepository $permissions,
        private InviteTokensStore $inviteTokensStore
    ) {
    }

    public function fromRequest(Request $request): void
    {
        // Clone the auth user to a new instance to prevent errors
        // when trying to serialize accessToken model during tests
        $inviter = new User($request->user()->toArray()); // @phpstan-ignore-line

        $inviteeEmail = $request->input('email');

        $rateLimiterKey = "invites:{$inviter->id}:{$inviteeEmail}";

        $invitationSent = RateLimiter::attempt(
            key: $rateLimiterKey,
            maxAttempts: 1,
            callback: function () use ($request, $inviteeEmail, $inviter) {
                $folder = $this->fetchFolderAndAttributesForValidation($request->integer('folder_id'), $inviteeEmail);

                if (is_null($folder)) {
                    throw new FolderNotFoundException();
                }

                $this->validateAction($folder, $inviter, $request);

                $this->sendInvitation(
                    $folder,
                    $folder->inviteeId,
                    $inviteeEmail,
                    $inviter,
                    UAC::fromRequest($request, 'permissions')
                );
            }
        );

        if (!$invitationSent) {
            throw new ThrottleRequestsException(
                message: 'TooManySentInvites',
                headers: ['resend-invite-after' => RateLimiter::availableIn($rateLimiterKey)]
            );
        }
    }

    private function fetchFolderAndAttributesForValidation(int $folderId, string $inviteeEmail): ?Folder
    {
        return Folder::onlyAttributes(['id', 'user_id', 'name', 'collaboratorsCount', 'visibility'])
            ->tap(new WhereFolderOwnerExists())
            ->tap(new DisabledActionScope(Permission::INVITE_USER))
            ->addSelect([
                'inviteeId' => User::select('users.id')
                    ->leftJoin('users_emails', 'users.id', '=', 'users_emails.user_id')
                    ->where('users.email', $inviteeEmail)
                    ->orWhere('users_emails.email', $inviteeEmail)
            ])
            ->addSelect([
                'collaboratorExists' => FolderCollaborator::select('id')
                    ->whereColumn('folder_id', 'folders.id')
                    ->whereColumn('folders_collaborators.collaborator_id', 'inviteeId')
            ])
            ->addSelect([
                'inviteeIsBanned' => BannedCollaborator::query()
                    ->select('id')
                    ->whereColumn('folder_id', 'folders.id')
                    ->whereColumn('user_id', 'inviteeId')
            ])
            ->find($folderId);
    }

    private function validateAction(Folder $folder, User $inviter, Request $request): void
    {
        $this->ensureUserHasPermissionToPerformAction($folder, $inviter, $request);

        UserNotFoundException::throwIf(!$folder->inviteeId);

        FolderCollaboratorsLimitExceededException::throwIfExceeded($folder->collaboratorsCount);

        $this->ensureIsNotSendingInvitationSelf($folder->inviteeId, $inviter);

        $this->ensureInviteeIsNotAlreadyACollaborator($folder);

        $this->ensureIsNotSendingInviteToABannedCollaborator($folder);
    }

    private function ensureUserHasPermissionToPerformAction(Folder $folder, User $inviter, Request $request): void
    {
        $folderBelongsToAuthUser = $folder->user_id === auth()->id();

        try {
            FolderNotFoundException::throwIf(!$folderBelongsToAuthUser);

            if ($folder->visibility->isPrivate()) {
                throw HttpException::forbidden(['message' => 'CannotAddCollaboratorsToPrivateFolder']);
            }

            if ($folder->visibility->isPasswordProtected()) {
                throw HttpException::forbidden(['message' => 'CannotAddCollaboratorsToPasswordProtectedFolder']);
            }
        } catch (FolderNotFoundException $e) {
            $userFolderPermissions = $this->permissions->all($inviter->id, $folder->id);

            if ($userFolderPermissions->isEmpty()) {
                throw $e;
            }

            if (!$userFolderPermissions->canInviteUser()) {
                throw new PermissionDeniedException(Permission::INVITE_USER);
            }

            if ($request->filled('permissions')) {
                throw HttpException::forbidden(['message' => 'CollaboratorCannotSendInviteWithPermissions']);
            }

            if ($folder->actionIsDisable) {
                throw new FolderActionDisabledException(Permission::INVITE_USER);
            }
        }
    }

    private function ensureIsNotSendingInvitationSelf(?int $inviteeId, User $inviter): void
    {
        if ($inviteeId === $inviter->id) {
            throw HttpException::forbidden(['message' => 'CannotSendInviteToSelf']);
        }
    }

    private function sendInvitation(
        Folder $folder,
        int $inviteeId,
        string $inviteeEmail,
        User $inviter,
        UAC $permissions
    ): void {
        $this->inviteTokensStore->store(
            $token = (string) Str::uuid(),
            $inviter->id,
            $inviteeId,
            $folder->id,
            $permissions
        );

        Mail::to($inviteeEmail)->later(5, new InvitationMail($inviter, $folder, $token));
    }

    private function ensureInviteeIsNotAlreadyACollaborator(Folder $folder): void
    {
        if ($folder->collaboratorExists || $folder->inviteeId === $folder->user_id) {
            throw HttpException::conflict(['message' => 'UserAlreadyACollaborator']);
        }
    }

    private function ensureIsNotSendingInviteToABannedCollaborator(Folder $folder): void
    {
        if ($folder->inviteeIsBanned) {
            throw HttpException::forbidden(['message' => 'UserBanned']);
        }
    }
}
