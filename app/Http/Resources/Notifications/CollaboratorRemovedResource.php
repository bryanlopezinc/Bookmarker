<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\CollaboratorRemovedNotificationData;
use App\Models\DatabaseNotification;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

final class CollaboratorRemovedResource extends JsonResource
{
    public function __construct(private DatabaseNotification $notification)
    {
    }

    #[Override]
    public function toArray($request)
    {
        $data = CollaboratorRemovedNotificationData::fromArray($this->notification->data);

        $collaborator = $this->notification->resources->findUserById($data->activityLog->collaboratorRemoved->id);
        $removedBy = $this->notification->resources->findUserById($data->activityLog->collaborator->id);
        $folder = $this->notification->resources->findFolderById($data->folder->id);

        return [
            'type' => 'CollaboratorRemovedNotification',
            'attributes' => [
                'id'           => $this->notification->id,
                'collaborator' => new UserResource($data->activityLog->collaboratorRemoved, $collaborator),
                'removed_by'   => new UserResource($data->activityLog->collaborator, $removedBy),
                'folder'       => new FolderResource($data->folder, $folder),
                'message'      => $this->notificationMessage($collaborator, $removedBy, $folder),
                'notified_on'   => $this->notification->created_at,
            ]
        ];
    }

    private function notificationMessage(User $collaborator, User $removedBy, Folder $folder): string
    {
        $data = CollaboratorRemovedNotificationData::fromArray($this->notification->data);

        return sprintf('%s removed%s %s from %s.', ...[
            $removedBy->getFullNameOr($data->activityLog->collaborator)->present(),
            $data->wasBanned ? ' and banned' : '',
            $collaborator->getFullNameOr($data->activityLog->collaboratorRemoved)->present(),
            $folder->getNameOr($data->folder)->present()
        ]);
    }
}
