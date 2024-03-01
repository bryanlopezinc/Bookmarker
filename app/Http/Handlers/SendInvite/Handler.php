<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Models\User;
use App\Models\Folder;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;

final class Handler
{
    /**
     * @var array<class-string<HandlerInterface>>
     */
    private const HANDLERS = [
        Constraints\FolderExistConstraint::class,
        Constraints\MustBeACollaboratorConstraint::class,
        Constraints\PermissionConstraint::class,
        Constraints\FolderVisibilityConstraint::class,
        CollaboratorCannotSendInviteWithPermissionsConstraint::class,
        Constraints\FeatureMustBeEnabledConstraint::class,
        InviteeExistConstraint::class,
        Constraints\CollaboratorsLimitConstraint::class,
        Constraints\UserDefinedFolderCollaboratorsLimitConstraint::class,
        CannotSendInviteToSelfConstraint::class,
        UniqueCollaboratorConstraint::class,
        CannotSendInviteToFolderOwnerConstraint::class,
        BannedCollaboratorConstraint::class,
        RateLimitConstraint::class,
        SendInvitationToInvitee::class
    ];

    private RequestHandlersQueue $requestHandlersQueue;

    public function __construct()
    {
        $this->requestHandlersQueue = new RequestHandlersQueue(self::HANDLERS);
    }

    public function handle(string $inviteeEmail, int $folderId): void
    {
        $query = Folder::query()->select(['id']);

        $invitee = User::select(['users.id'])
            ->leftJoin('users_emails', 'users.id', '=', 'users_emails.user_id')
            ->where('users.email', $inviteeEmail)
            ->orWhere('users_emails.email', $inviteeEmail)
            ->firstOr(fn () => new User());

        $this->requestHandlersQueue->scope($query, function ($handler) use ($invitee) {
            if ($handler instanceof InviteeAwareInterface) {
                $handler->setInvitee($invitee);
            }
        });

        $folder = $query->findOr($folderId, callback: fn () => new Folder());

        $this->requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }
}
