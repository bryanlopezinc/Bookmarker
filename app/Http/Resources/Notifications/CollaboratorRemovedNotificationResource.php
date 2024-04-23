<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\CollaboratorRemovedNotificationData;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

final class CollaboratorRemovedNotificationResource extends JsonResource
{
    public function __construct(private CollaboratorRemovedNotificationData $notification)
    {
    }

    #[Override]
    public function toArray($request)
    {
        return [
            'type' => 'CollaboratorRemovedNotification',
            'attributes' => [
                'id'         => $this->notification->uuid,
                'notified_on' => $this->notification->notifiedOn,
                'message'    => $this->notificationMessage(),
                'folder'     => [
                    'id'     => $this->notification->folderId->present(),
                    'exists' => $this->notification->folder !== null,
                ],
                'collaborator' => [
                    'id'     => $this->notification->collaboratorId->present(),
                    'exists' => $this->notification->collaborator !== null,
                ],
                'removed_by' => [
                    'id'     => $this->notification->removedById->present(),
                    'exists' => $this->notification->removedBy !== null,
                ],
            ]
        ];
    }

    private function notificationMessage(): string
    {
        return sprintf('%s removed%s %s from %s.', ...[
            $this->notification->removedBy?->full_name?->present() ?: $this->notification->removedByFullName->present(),
            $this->notification->wasBanned ? ' and banned' : '',
            $this->notification->collaborator?->full_name?->present() ?: $this->notification->collaboratorFullName->present(),
            $this->notification->folder?->name?->present() ?: $this->notification->folderName->present()
        ]);
    }
}
