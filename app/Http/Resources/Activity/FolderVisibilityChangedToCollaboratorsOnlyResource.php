<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\FolderActivity;
use App\DataTransferObjects\Activities\FolderVisibilityChangedToCollaboratorsOnlyActivityLogData as ActivityLogData;
use App\Enums\FolderVisibility;
use App\Models\User;

final class FolderVisibilityChangedToCollaboratorsOnlyResource extends JsonResource
{
    public function __construct(private readonly FolderActivity $folderActivity)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $authUser = User::fromRequest($request);
        $activityLog = ActivityLogData::fromArray($this->folderActivity->data);

        $collaborator = $this->folderActivity->resources->findUserById($activityLog->collaborator->id);

        $collaboratorName = $collaborator->getFullNameOr($activityLog->collaborator)->present();

        if ($authUser->id === $activityLog->collaborator->id) {
            $collaboratorName = 'You';
        }

        $visibility = FolderVisibility::COLLABORATORS->toWord();

        return [
            'type'       => 'FolderVisibilityChangedToCollaboratorsOnlyActivity',
            'attributes' => [
                'event_time'   => $this->folderActivity->created_at,
                'message'      => "{$collaboratorName} changed folder visibility to {$visibility}",
                'collaborator' => new UserResource($activityLog->collaborator, $collaborator),
            ]
        ];
    }
}
