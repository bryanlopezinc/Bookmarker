<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\FolderIconChangedNotificationData;
use App\Http\Resources\ResourceMessages\IconChanged;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\DatabaseNotification;
use App\Models\User;

final class FolderIconChangedResource extends JsonResource
{
    public function __construct(private DatabaseNotification $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $data = FolderIconChangedNotificationData::fromArray($this->notification->data);

        $collaborator = $this->notification->resources->findUserById($data->activityLog->collaborator->id);

        $folder = $this->notification->resources->findFolderById($data->folder->id);

        return [
            'type'       => 'FolderIconChangedNotification',
            'attributes' => [
                'id'           => $this->notification->id,
                'collaborator' => new UserResource($data->activityLog->collaborator, $collaborator),
                'folder'       => new FolderResource($data->folder, $folder),
                'notified_on'   => $this->notification->created_at,
                'message'      => new IconChanged($collaborator, new User(), $data->activityLog),
            ]
        ];
    }
}
