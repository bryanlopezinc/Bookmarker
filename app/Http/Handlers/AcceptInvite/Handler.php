<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\Cache\FolderInviteDataRepository;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Contracts\AcceptFolderInviteRequestHandlerInterface;
use App\Http\Handlers\RequestHandlersQueue;

final class Handler implements AcceptFolderInviteRequestHandlerInterface
{
    /**
     * @var array<class-string<HandlerInterface>>
     */
    private const HANDLERS = [
        Constraints\FolderExistConstraint::class,
        HasNotAlreadyAcceptedInviteValidator::class,
        InviterAndInviteeExistsValidator::class,
        Constraints\FolderVisibilityConstraint::class,
        Constraints\CollaboratorsLimitConstraint::class,
        CreateNewCollaborator::class,
        SendNewCollaboratorNotification::class,
    ];

    /**
     * @var RequestHandlersQueue<HandlerInterface>
     */
    private RequestHandlersQueue $requestHandlersQueue;

    private readonly FolderInviteDataRepository $folderInviteDataRepository;

    public function __construct(FolderInviteDataRepository $inviteTokensStore = null)
    {
        $this->folderInviteDataRepository = $inviteTokensStore ?: app(FolderInviteDataRepository::class);

        $this->requestHandlersQueue = new RequestHandlersQueue(self::HANDLERS);
    }

    public function handle(string $inviteId): void
    {
        //Make a least one select to prevent fetching all columns
        //as other handlers would use addSelect() ideally.
        $query = Folder::query()->select(['id']);

        $invitationData = $this->folderInviteDataRepository->get($inviteId);

        $this->requestHandlersQueue->scope($query, function ($handler) use ($invitationData) {
            if ($handler instanceof InvitationDataAwareInterface) {
                $handler->setInvitationData($invitationData);
            }
        });

        $folder = $query->findOr($invitationData->folderId, callback: fn () => new Folder());

        $this->requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }
}
