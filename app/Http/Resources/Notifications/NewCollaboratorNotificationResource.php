<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DataTransferObjects\Notifications\NewCollaboratorNotificationData;

final class NewCollaboratorNotificationResource extends JsonResource
{
    public function __construct(private NewCollaboratorNotificationData $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'type'       => 'CollaboratorAddedToFolderNotification',
            'attributes' => [
                'id'                      => $this->notification->uuid,
                'collaborator_exists'     => $this->notification->collaborator !== null,
                'folder_exists'           => $this->notification->folder !== null,
                'new_collaborator_exists' => $this->notification->newCollaborator !== null,
                'message'                 => $this->notificationMessage(),
                'notified_on'              => $this->notification->notifiedOn,
                'collaborator_id'         => $this->notification->collaboratorId->present(),
                'new_collaborator_id'     => $this->notification->newCollaboratorId->present(),
                'folder_id'               => $this->notification->folderId->present(),
            ]
        ];
    }

    private function notificationMessage(): string
    {
        return sprintf('%s added %s to %s folder.', ...[
            $this->notification->collaborator?->full_name?->present() ?: $this->notification->collaboratorFullName->present(),
            $this->notification->newCollaborator?->full_name?->present() ?: $this->notification->newCollaboratorFullName->present(),
            $this->notification->folder?->name?->present() ?: $this->notification->folderName->present()
        ]);
    }
}
