<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Cache\InviteTokensStore;
use App\Exceptions\FolderNotFoundException;
use App\Models\{BannedCollaborator, Folder, User};
use App\Exceptions\HttpException;
use App\Http\Requests\SendFolderCollaborationInviteRequest as Request;
use App\Mail\FolderCollaborationInviteMail as InvitationMail;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\Repositories\UserRepository;
use App\UAC;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\{RateLimiter, Mail};
use Illuminate\Support\Str;

final class SendFolderCollaborationInviteService
{
    public function __construct(
        private FetchFolderService $folderRepository,
        private UserRepository $userRepository,
        private FolderPermissionsRepository $permissions,
        private InviteTokensStore $inviteTokensStore
    ) {
    }

    public function fromRequest(Request $request): void
    {
        // Clone the auth user to a new instance to prevent errors
        // when trying to serialize accessToken model during tests
        $inviter = new User($request->user('api')->toArray()); // @phpstan-ignore-line

        $folder = $this->folderRepository->find($request->integer('folder_id'), ['id', 'user_id', 'name']);

        $invitee = $this->userRepository->findByEmailOrSecondaryEmail(
            $inviteeEmail = $request->input('email'),
            ['email', 'id']
        );

        $this->ensureUserHasPermissionToPerformAction($folder, $inviter, $request);
        $this->ensureIsNotSendingInvitationSelf($inviteeEmail, $inviter);
        $this->ensureInviteeIsNotAlreadyACollaborator($invitee, $folder);
        $this->ensureIsNotSendingInviteToABannedCollaborator($invitee, $folder);

        $invitationMailSent = RateLimiter::attempt(
            $this->key($inviter, $inviteeEmail),
            1,
            $this->sendInvitationCallback(
                $folder,
                $invitee,
                $inviter,
                UAC::fromRequest($request, 'permissions')
            )
        );

        if ($invitationMailSent === false) {
            throw new ThrottleRequestsException('Too Many Requests');
        }
    }

    private function ensureUserHasPermissionToPerformAction(Folder $folder, User $inviter, Request $request): void
    {
        try {
            FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);
        } catch (FolderNotFoundException $e) {
            $userFolderPermissions = $this->permissions->getUserAccessControls($inviter->id, $folder->id);

            if ($userFolderPermissions->isEmpty()) {
                throw $e;
            }

            if (!$userFolderPermissions->canInviteUser()) {
                throw HttpException::forbidden(['message' => 'NoSendInvitePermission']);
            }

            if ($request->filled('permissions')) {
                throw HttpException::forbidden([
                    'message' => 'CollaboratorCannotSendInviteWithPermissions'
                ]);
            }
        }
    }

    private function ensureIsNotSendingInvitationSelf(string $inviteeEmail, User $inviter): void
    {
        $inviterPrimaryAndSecondaryEmails = array_merge(
            [$inviter->email],
            $this->userRepository->getUserSecondaryEmails($inviter->id)
        );

        foreach ($inviterPrimaryAndSecondaryEmails as $inviterEmail) {
            if ($inviterEmail === $inviteeEmail) {
                throw HttpException::forbidden([
                    'message' => 'CannotSendInviteToSelf'
                ]);
            }
        }
    }

    private function key(User $inviter, string $inviteeEmail): string
    {
        return implode(':', ['f-col-invites', $inviter->id, $inviteeEmail]);
    }

    private function sendInvitationCallback(Folder $folder, User $invitee, User $inviter, UAC $permissions): \Closure
    {
        return function () use ($folder, $invitee, $inviter, $permissions) {
            $this->inviteTokensStore->store(
                $token = (string) Str::uuid(),
                $inviter->id,
                $invitee->id,
                $folder->id,
                $permissions
            );

            Mail::to($invitee->email)->later(20, new InvitationMail($inviter, $folder, $token));
        };
    }

    private function ensureInviteeIsNotAlreadyACollaborator(User $invitee, Folder $folder): void
    {
        $inviteeIsFolderOwner = $invitee->id === $folder->user_id;

        $inviteeIsACollaborator = $this->permissions->getUserAccessControls($invitee->id, $folder->id)->isNotEmpty();

        if ($inviteeIsACollaborator || $inviteeIsFolderOwner) {
            throw HttpException::conflict([
                'message' => 'UserAlreadyACollaborator'
            ]);
        }
    }

    private function ensureIsNotSendingInviteToABannedCollaborator(User $invitee, Folder $folder): void
    {
        $isBanned = BannedCollaborator::query()->where([
            'folder_id' => $folder->id,
            'user_id'   => $invitee->id
        ])->exists();

        if ($isBanned) {
            throw HttpException::forbidden(['message' => 'UserBanned']);
        }
    }
}
