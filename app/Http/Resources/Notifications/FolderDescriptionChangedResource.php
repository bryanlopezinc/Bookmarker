<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\FolderDescriptionChangedNotificationData;
use App\Http\Resources\ResourceMessages\DescriptionChanged;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\DatabaseNotification;
use App\Models\User;

final class FolderDescriptionChangedResource extends JsonResource
{
    public function __construct(private DatabaseNotification $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $data = FolderDescriptionChangedNotificationData::fromArray($this->notification->data);

        $collaborator = $this->notification->resources->findUserById($data->activityLog->collaborator->id);

        $folder = $this->notification->resources->findFolderById($data->folder->id);

        return [
            'type'       => 'FolderDescriptionChangedNotification',
            'attributes' => [
                'id'           => $this->notification->id,
                'collaborator' => new UserResource($data->activityLog->collaborator, $collaborator),
                'folder'       => new FolderResource($data->folder, $folder),
                'notified_on'   => $this->notification->created_at,
                'message'      => new DescriptionChanged($collaborator, new User(), $data->activityLog),
            ]
        ];
    }
}
