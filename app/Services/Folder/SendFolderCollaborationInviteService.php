<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Cache\InviteTokensStore;
use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Builders\UserBuilder;
use App\DataTransferObjects\{Folder, User};
use App\Exceptions\HttpException;
use App\Exceptions\UserNotFoundHttpException;
use App\Http\Requests\SendFolderCollaborationInviteRequest as Request;
use App\Mail\FolderCollaborationInviteMail as Invite;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\Repositories\UserRepository;
use App\UAC;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\{ResourceID, Email, Uuid};
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\{RateLimiter, Mail};
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;

final class SendFolderCollaborationInviteService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private UserRepository $userRepository,
        private FolderPermissionsRepository $permissions,
        private InviteTokensStore $inviteTokensStore
    ) {
    }

    public function fromRequest(Request $request): void
    {
        $inviter = UserBuilder::fromModel(auth('api')->user())->build(); // @phpstan-ignore-line
        $folder = $this->folderRepository->find(ResourceID::fromRequest($request, 'folder_id'), FolderAttributes::only('name,id,user_id'));
        $invitee = $this->retrieveInviteeInfo($inviteeEmail = new Email($request->input('email')));

        $this->ensureUserHasPermissionToPerformAction($folder, $inviter, $request);
        $this->ensureIsNotSendingInvitationSelf($inviteeEmail, $inviter);
        $this->ensureUserIsNotSendingInvitationToFolderOwner($folder, $invitee);
        $this->ensureInviteeIsNotAlreadyACollaborator($invitee, $folder);

        $invitationMailSent = RateLimiter::attempt($this->key($inviter, $inviteeEmail), 1, $this->sendInvitationCallback(
            $folder,
            $invitee,
            $inviter,
            UAC::fromRequest($request, 'permissions')
        ));

        if ($invitationMailSent === false) {
            throw new ThrottleRequestsException('Too Many Requests');
        }
    }

    private function ensureUserHasPermissionToPerformAction(Folder $folder, User $inviter, Request $request): void
    {
        try {
            (new EnsureAuthorizedUserOwnsResource)($folder);
        } catch (SymfonyHttpException $e) {
            $canInviteUser = $this->permissions->getUserAccessControls($inviter->id, $folder->folderID)->canInviteUser();

            if (!$canInviteUser) {
                throw $e;
            }

            if ($request->filled('permissions')) {
                throw HttpException::forbidden([
                    'message' => 'only folder owner can send invites with permissions'
                ]);
            }
        }
    }

    /**
     * Ensure user with "inviteUserPermission" permission is not sending invite to folder owner
     */
    private function ensureUserIsNotSendingInvitationToFolderOwner(Folder $folder, User $invitee): void
    {
        if ($folder->ownerID->equals($invitee->id)) {
            throw HttpException::forbidden([
                'message' => 'Cannot send invitation to folder owner'
            ]);
        }
    }

    private function retrieveInviteeInfo(Email $inviteeEmail): User
    {
        $invitee = $this->userRepository->findByEmailOrSecondaryEmail(
            $inviteeEmail,
            \App\QueryColumns\UserAttributes::only('id,email')
        );

        if ($invitee === false) {
            throw new UserNotFoundHttpException;
        }

        return $invitee;
    }

    private function ensureIsNotSendingInvitationSelf(Email $inviteeEmail, User $inviter): void
    {
        $inviterPrimaryAndSecondaryEmails = array_merge(
            [$inviter->email],
            $this->userRepository->getUserSecondaryEmails($inviter->id)
        );

        foreach ($inviterPrimaryAndSecondaryEmails as $inviterEmail) {
            if ($inviterEmail->equals($inviteeEmail)) {
                throw HttpException::forbidden([
                    'message' => 'Cannot send invite to self'
                ]);
            }
        }
    }

    private function key(User $inviter, Email $inviteeEmail): string
    {
        return implode(':', ['f-col-invites', $inviter->id->value(), $inviteeEmail->value]);
    }

    private function sendInvitationCallback(Folder $folder, User $invitee, User $inviter, UAC $permissions): \Closure
    {
        return function () use ($folder, $invitee, $inviter, $permissions) {
            $this->inviteTokensStore->store(
                $token = Uuid::generate(),
                $inviter->id,
                $invitee->id,
                $folder->folderID,
                $permissions
            );

            Mail::to($invitee->email->value)->queue(new Invite($inviter, $folder, $invitee, $token));
        };
    }

    private function ensureInviteeIsNotAlreadyACollaborator(User $invitee, Folder $folder): void
    {
        $collaboratorExist = $this->permissions->getUserAccessControls($invitee->id, $folder->folderID)->hasAnyPermission();

        if ($collaboratorExist) {
            throw HttpException::conflict([
                'message' => 'User already a collaborator'
            ]);
        }
    }
}
