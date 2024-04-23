<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DataTransferObjects\Notifications\CollaboratorExitNotificationData;

final class CollaboratorExitNotificationResource extends JsonResource
{
    public function __construct(private CollaboratorExitNotificationData $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'type'       => 'CollaboratorExitNotification',
            'attributes' => [
                'id'                  => $this->notification->uuid,
                'collaborator_exists' =>  $this->notification->collaborator !== null,
                'folder_exists'       =>  $this->notification->folder !== null,
                'notified_on'          => $this->notification->notifiedOn,
                'collaborator_id'     =>  $this->notification->collaboratorId->present(),
                'folder_id'           =>  $this->notification->folderId->present(),
                'message'             => $this->notificationMessage(),
            ]
        ];
    }

    private function notificationMessage(): string
    {
        return sprintf('%s left %s folder.', ...[
            $this->notification->collaborator?->full_name?->present() ?: $this->notification->collaboratorFullName->present(),
            $this->notification->folder?->name?->present() ?: $this->notification->folderName->present()
        ]);
    }
}
