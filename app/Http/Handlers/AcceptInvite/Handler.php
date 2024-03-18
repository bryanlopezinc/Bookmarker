<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\Cache\FolderInviteDataRepository;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Contracts\AcceptFolderInviteRequestHandlerInterface;
use App\Enums\Feature;
use App\Http\Handlers\RequestHandlersQueue;

final class Handler implements AcceptFolderInviteRequestHandlerInterface
{
    /**
     * @var RequestHandlersQueue<HandlerInterface>
     */
    private RequestHandlersQueue $requestHandlersQueue;

    private readonly FolderInviteDataRepository $folderInviteDataRepository;

    public function __construct(FolderInviteDataRepository $inviteTokensStore = null)
    {
        $this->folderInviteDataRepository = $inviteTokensStore ?: app(FolderInviteDataRepository::class);

        $this->requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers());
    }

    public function handle(string $inviteId): void
    {
        $invitationData = $this->folderInviteDataRepository->get($inviteId);

        //Make a least one select to prevent fetching all columns
        //as other handlers would use addSelect() ideally.
        $query = Folder::query()->select(['id'])->whereKey($invitationData->folderId);

        $this->requestHandlersQueue->scope($query, function ($handler) use ($invitationData) {
            if ($handler instanceof InvitationDataAwareInterface) {
                $handler->setInvitationData($invitationData);
            }
        });

        $folder = $query->firstOrNew();

        $this->requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new HasNotAlreadyAcceptedInviteValidator(),
            new InviterAndInviteeExistsValidator(),
            new Constraints\FolderVisibilityConstraint(),
            new Constraints\CollaboratorsLimitConstraint(),
            new Constraints\UserDefinedFolderCollaboratorsLimitConstraint(),
            new InviterMustBeAnActiveCollaboratorConstraint(),
            new InviterMustStillHaveRequiredPermissionConstraint(),
            new Constraints\FeatureMustBeEnabledConstraint(null, Feature::JOIN_FOLDER),
            new CreateNewCollaborator(),
            new SendNewCollaboratorNotification(),
        ];
    }
}
