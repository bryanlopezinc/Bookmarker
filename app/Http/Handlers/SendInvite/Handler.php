<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Models\User;
use App\Models\Folder;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\DataTransferObjects\SendInviteRequestData;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;

final class Handler
{
    public function handle(int $folderId, SendInviteRequestData $data): void
    {
        $query = Folder::query()->select(['id'])->whereKey($folderId);

        $invitee = User::select(['users.id'])
            ->leftJoin('users_emails', 'users.id', '=', 'users_emails.user_id')
            ->where('users.email', $data->inviteeEmail)
            ->orWhere('users_emails.email', $data->inviteeEmail)
            ->firstOrNew();

        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data, $invitee));

        $requestHandlersQueue->scope($query);

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(SendInviteRequestData $data, User $invitee): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new RateLimitConstraint($data),
            new Constraints\MustBeACollaboratorConstraint($data->authUser),
            new Constraints\PermissionConstraint($data->authUser, Permission::INVITE_USER),
            new Constraints\FolderVisibilityConstraint(),
            new CollaboratorCannotSendInviteWithPermissionsOrRolesConstraint($data),
            new Constraints\FeatureMustBeEnabledConstraint($data->authUser, Feature::SEND_INVITES),
            new InviteeExistConstraint($invitee),
            new Constraints\CollaboratorsLimitConstraint(),
            new Constraints\UserDefinedFolderCollaboratorsLimitConstraint(),
            new CannotSendInviteToSelfConstraint($data, $invitee),
            new UniqueCollaboratorConstraint($invitee),
            new CannotSendInviteToFolderOwnerConstraint($invitee),
            new CannotSendInviteToABannedCollaboratorConstraint($invitee),
            new ValidRolesConstraint($data),
            new SendInvitationToInvitee($data, $invitee),
            new HitRateLimit($data)
        ];
    }
}
