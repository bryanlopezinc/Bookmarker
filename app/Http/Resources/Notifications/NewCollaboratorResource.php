<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DataTransferObjects\Notifications\NewCollaboratorNotificationData;
use App\Models\DatabaseNotification;
use App\Models\Folder;
use App\Models\User;

final class NewCollaboratorResource extends JsonResource
{
    public function __construct(private DatabaseNotification $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $data = NewCollaboratorNotificationData::fromArray($this->notification->data);

        $collaborator = $this->notification->resources->findUserById($data->collaborator->id);
        $newCollaborator = $this->notification->resources->findUserById($data->newCollaborator->id);

        $folder = $this->notification->resources->findFolderById($data->folder->id);

        return [
            'type'       => 'CollaboratorAddedToFolderNotification',
            'attributes' => [
                'id'               => $this->notification->id,
                'collaborator'     => new UserResource($data->collaborator, $collaborator),
                'new_collaborator' => new UserResource($data->newCollaborator, $newCollaborator),
                'folder'           => new FolderResource($data->folder, $folder),
                'message'          => $this->notificationMessage($collaborator, $newCollaborator, $folder),
                'notified_on'       => $this->notification->created_at,
            ]
        ];
    }

    private function notificationMessage(User $collaborator, User $newCollaborator, Folder $folder): string
    {
        $data = NewCollaboratorNotificationData::fromArray($this->notification->data);

        return sprintf('%s added %s to %s folder.', ...[
            $collaborator->getFullNameOr($data->collaborator)->present(),
            $newCollaborator->getFullNameOr($data->newCollaborator)->present(),
            $folder->getNameOr($data->folder)->present()
        ]);
    }
}
