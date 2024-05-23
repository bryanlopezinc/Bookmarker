<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\FolderNameChangedNotificationData;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ResourceMessages\NameChanged;
use App\Models\DatabaseNotification;

final class FolderNameChangedResource extends JsonResource
{
    public function __construct(private DatabaseNotification $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $data = FolderNameChangedNotificationData::fromArray($this->notification->data);

        $collaborator = $this->notification->resources->findUserById($data->activityLog->collaborator->id);

        $folder = $this->notification->resources->findFolderById($data->folder->id);

        return [
            'type'       => 'FolderNameChangedNotification',
            'attributes' => [
                'id'           => $this->notification->id,
                'collaborator' => new UserResource($data->activityLog->collaborator, $collaborator),
                'folder'       => new FolderResource($data->folder, $folder),
                'notified_on'   => $this->notification->created_at,
                'message'      => new NameChanged($collaborator, $data->activityLog),
            ]
        ];
    }
}
