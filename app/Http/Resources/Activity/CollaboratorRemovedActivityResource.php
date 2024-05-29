<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\FolderActivity;
use App\Models\User;
use App\DataTransferObjects\Activities\CollaboratorRemovedActivityLogData as ActivityLogData;

final class CollaboratorRemovedActivityResource extends JsonResource
{
    public function __construct(private readonly FolderActivity $folderActivity)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $activityLog = ActivityLogData::fromArray($this->folderActivity->data);

        $collaborator = $this->folderActivity->resources->findUserById($activityLog->collaborator->id);

        $collaboratorRemoved = $this->folderActivity->resources->findUserById($activityLog->collaboratorRemoved->id);

        return [
            'type'       => 'CollaboratorRemovedActivity',
            'attributes' => [
                'event_time'           => $this->folderActivity->created_at,
                'message'              => $this->message(User::fromRequest($request), $collaborator, $collaboratorRemoved),
                'collaborator'         => new UserResource($activityLog->collaborator, $collaborator),
                'collaborator_removed' => new UserResource($activityLog->collaboratorRemoved, $collaboratorRemoved),
            ]
        ];
    }

    private function message(User $authUser, User $collaborator, User $collaboratorRemoved): string
    {
        $activityLog = ActivityLogData::fromArray($this->folderActivity->data);

        $collaboratorWasRemovedByAuthUser = $authUser->id === $activityLog->collaborator->id;

        $removedCollaboratorName = $collaboratorRemoved->getFullNameOr($activityLog->collaboratorRemoved)->present();

        return str(":collaboratorName: removed {$removedCollaboratorName}")

            ->when(
                value: $collaboratorWasRemovedByAuthUser,
                callback: fn ($message) => $message->replace(':collaboratorName:', 'You'),
                default: function ($message) use ($activityLog, $collaborator) {
                    return $message->replace(
                        ':collaboratorName:',
                        $collaborator->getFullNameOr($activityLog->collaborator)->present()
                    );
                }
            )

            ->toString();
    }
}
