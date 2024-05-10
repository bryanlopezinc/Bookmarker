<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Models\User;
use App\Models\Folder;
use App\DataTransferObjects\SendInviteRequestData;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;
use App\Http\Handlers\SuspendCollaborator\SuspendedCollaboratorFinder;
use App\Models\Scopes\WherePublicIdScope;
use App\ValueObjects\PublicId\FolderPublicId;

final class Handler
{
    public function handle(FolderPublicId $folderId, SendInviteRequestData $data): void
    {
        $query = Folder::query()->select(['id'])->tap(new WherePublicIdScope($folderId));

        $invitee = User::select(['users.id'])
            ->leftJoin('users_emails', 'users.id', '=', 'users_emails.user_id')
            ->where('users.email', $data->inviteeEmail)
            ->orWhere('users_emails.email', $data->inviteeEmail)
            ->firstOrNew();

        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data, $invitee));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(SendInviteRequestData $data, User $invitee): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new RateLimitConstraint($data),
            $suspendedCollaboratorRepository = new SuspendedCollaboratorFinder($data->authUser),
            new Constraints\MustBeACollaboratorConstraint($data->authUser),
            new Constraints\PermissionConstraint($data->authUser, Permission::INVITE_USER),
            new Constraints\FolderVisibilityConstraint(),
            new CollaboratorCannotSendInviteWithPermissionsOrRolesConstraint($data),
            new Constraints\FeatureMustBeEnabledConstraint($data->authUser, Feature::SEND_INVITES),
            new Constraints\MustNotBeSuspendedConstraint($suspendedCollaboratorRepository),
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
