<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DataTransferObjects\Notifications\CollaboratorExitNotificationData;
use App\Models\DatabaseNotification;
use App\Models\Folder;
use App\Models\User;

final class CollaboratorExitResource extends JsonResource
{
    public function __construct(private DatabaseNotification $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $data = CollaboratorExitNotificationData::fromArray($this->notification->data);

        $collaborator = $this->notification->resources->findUserById($data->activityLog->collaborator->id);

        $folder = $this->notification->resources->findFolderById($data->folder->id);

        return [
            'type'       => 'CollaboratorExitNotification',
            'attributes' => [
                'id'           => $this->notification->id,
                'collaborator' => new UserResource($data->activityLog->collaborator, $collaborator),
                'folder'       => new FolderResource($data->folder, $folder),
                'message'      => $this->notificationMessage($collaborator, $folder),
                'notified_on'   => $this->notification->created_at,
            ]
        ];
    }

    private function notificationMessage(User $collaborator, Folder $folder): string
    {
        $data = CollaboratorExitNotificationData::fromArray($this->notification->data);

        return sprintf('%s left %s folder.', ...[
            $collaborator->getFullNameOr($data->activityLog->collaborator)->present(),
            $folder->getNameOr($data->folder)->present()
        ]);
    }
}
