<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\Cache\FolderInviteDataRepository;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Contracts\AcceptFolderInviteRequestHandlerInterface;
use App\DataTransferObjects\FolderInviteData;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Http\Handlers\CollaboratorMetricsRecorder;
use App\Http\Handlers\RequestHandlersQueue;

final class Handler implements AcceptFolderInviteRequestHandlerInterface
{
    private readonly FolderInviteDataRepository $folderInviteDataRepository;

    public function __construct(FolderInviteDataRepository $inviteTokensStore = null)
    {
        $this->folderInviteDataRepository = $inviteTokensStore ?: app(FolderInviteDataRepository::class);
    }

    public function handle(string $inviteId): void
    {
        $invitationData = $this->folderInviteDataRepository->get($inviteId);

        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($invitationData));

        //Make a least one select to prevent fetching all columns
        //as other handlers would use addSelect() ideally.
        $query = Folder::query()->select(['id'])->whereKey($invitationData->folderId);

        $requestHandlersQueue->scope($query);

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(FolderInviteData $payload): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new HasNotAlreadyAcceptedInviteValidator($payload),
            new InviterAndInviteeExistsValidator($payload),
            new Constraints\FolderVisibilityConstraint(),
            new Constraints\CollaboratorsLimitConstraint(),
            new Constraints\UserDefinedFolderCollaboratorsLimitConstraint(),
            new InviterMustBeAnActiveCollaboratorConstraint($payload),
            new InviterMustStillHaveRequiredPermissionConstraint($payload),
            new Constraints\FeatureMustBeEnabledConstraint(null, Feature::JOIN_FOLDER),
            new CreateNewCollaborator($payload),
            new SendNewCollaboratorNotification($payload),
            new CollaboratorMetricsRecorder(CollaboratorMetricType::COLLABORATORS_ADDED, $payload->inviterId)
        ];
    }
}
