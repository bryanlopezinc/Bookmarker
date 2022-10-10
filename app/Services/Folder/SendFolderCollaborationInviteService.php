<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Builders\UserBuilder;
use App\DataTransferObjects\Folder;
use App\DataTransferObjects\User;
use App\Exceptions\HttpException;
use App\Exceptions\UserNotFoundHttpException;
use App\Http\Requests\SendFolderCollaborationInviteRequest as Request;
use App\Mail\FolderCollaborationInviteMail;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\Repositories\UserRepository;
use App\FolderPermissions;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\Email;
use App\ValueObjects\ResourceID;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

final class SendFolderCollaborationInviteService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private UserRepository $userRepository,
        private FolderPermissionsRepository $folderPermissionsRepository
    ) {
    }

    public function fromRequest(Request $request): void
    {
        $inviter = UserBuilder::fromModel(auth('api')->user())->build(); // @phpstan-ignore-line

        $folder = $this->folderRepository->find(ResourceID::fromRequest($request, 'folder_id'), FolderAttributes::only('name,id,user_id'));
        $invitee = $this->findInviteeByEmail($inviteeEmail = new Email($request->input('email')));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $this->ensureIsNotSendingInvitionSelf($inviteeEmail, $inviter);
        $this->ensureInviteeIsNotAlreadyACollaborator($invitee, $folder);

        $invitationMailSent = RateLimiter::attempt($this->cacheKey($inviter, $inviteeEmail), 1, $this->sendInvitationCallback(
            $folder,
            $invitee,
            $inviter,
            FolderPermissions::fromRequest($request)
        ));

        if ($invitationMailSent === false) {
            throw new ThrottleRequestsException('Too Many Requests');
        }
    }

    private function findInviteeByEmail(Email $inviteeEmail): User
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

    private function ensureIsNotSendingInvitionSelf(Email $inviteeEmail, User $inviter): void
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

    private function cacheKey(User $inviter, Email $inviteeEmail): string
    {
        return implode(':', [
            'f-col-invites',
            $inviter->id->toInt(),
            $inviteeEmail->value
        ]);
    }

    private function sendInvitationCallback(Folder $folder, User $invitee, User $inviter, FolderPermissions $permissions): \Closure
    {
        return fn () => Mail::to($invitee->email->value)->queue(new FolderCollaborationInviteMail(
            $inviter,
            $folder,
            $invitee,
            $permissions
        ));
    }

    private function ensureInviteeIsNotAlreadyACollaborator(User $invitee, Folder $folder): void
    {
        $collaboratorExist = $this->folderPermissionsRepository->getFolderPermissions($invitee->id, $folder->folderID)->hasAnyPermission();

        if ($collaboratorExist) {
            throw HttpException::conflict([
                'message' => 'User already a collaborator'
            ]);
        }
    }
}
