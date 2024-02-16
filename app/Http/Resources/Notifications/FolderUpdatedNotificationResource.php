<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DataTransferObjects\Notifications\FolderUpdatedNotificationData;
use App\ValueObjects\FolderName;

final class FolderUpdatedNotificationResource extends JsonResource
{
    public function __construct(private FolderUpdatedNotificationData $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'type'       => 'FolderUpdatedNotification',
            'attributes' => [
                'id'                  => $this->notification->uuid,
                'collaborator_exists' => $this->notification->collaborator !== null,
                'folder_exists'       => $this->notification->folder !== null,
                'notified_on'          => $this->notification->notifiedOn,
                'collaborator_id'     => $this->notification->collaboratorId,
                'folder_id'           => $this->notification->folderId,
                'message'             => $this->notificationMessage(),
            ]
        ];
    }

    private function notificationMessage(): string
    {
        return sprintf('%s changed %s %s from %s to %s.', ...[
            $this->notification->collaborator?->full_name?->present() ?: $this->notification->collaboratorFullName->present(),
            $this->notification->folder?->name?->present() ?: $this->notification->folderName->present(),
            $this->notification->modifiedAttribute,
            (new FolderName($this->notification->changes['from']))->present(),
            (new FolderName($this->notification->changes['to']))->present(),
        ]);
    }
}
