<?php

declare(strict_types=1);

namespace App\Http\Resources\Activity;

use App\DataTransferObjects\Activities\DescriptionChangedActivityLogData as ActivityLogData;
use App\Http\Resources\ResourceMessages\DescriptionChanged;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\FolderActivity;
use App\Models\User;

final class FolderDescriptionChangedActivityResource extends JsonResource
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

        return [
            'type'       => 'FolderDescriptionChangedActivity',
            'attributes' => [
                'event_time'   => $this->folderActivity->created_at,
                'message'      => new DescriptionChanged($collaborator, User::fromRequest($request), $activityLog),
                'collaborator' => new UserResource($activityLog->collaborator, $collaborator),
            ]
        ];
    }
}
