<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\FolderActivity;
use App\DataTransferObjects\Activities\CollaboratorExitActivityLogData as ActivityLogData;

final class CollaboratorExitActivity extends JsonResource
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

        $collaboratorName = $collaborator->getFullNameOr($activityLog->collaborator)->present();

        return [
            'type'       => 'CollaboratorExitActivity',
            'attributes' => [
                'event_time'   => $this->folderActivity->created_at,
                'message'      => "{$collaboratorName} left",
                'collaborator' => new UserResource($activityLog->collaborator, $collaborator),
            ]
        ];
    }
}
