<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use Illuminate\Support\Str;
use App\ValueObjects\FolderName;
use Illuminate\Http\Resources\Json\JsonResource;
use App\DataTransferObjects\Notifications\FolderUpdatedNotificationData;

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
                'message'             => match ($this->notification->modifiedAttribute) {
                    'name'        => $this->folderNameChangedNotificationMessage(),
                    'description' => $this->folderDescriptionChangedNotificationMessage(),
                    default       => $this->folderIconChangedNotificationMessage(),
                },
            ]
        ];
    }

    private function folderNameChangedNotificationMessage(): string
    {
        return sprintf('%s changed %s name from %s to %s.', ...[
            $this->presentCollaboratorName(),
            $this->presentFolderName(),
            (new FolderName($this->notification->changes['from']))->present(),
            (new FolderName($this->notification->changes['to']))->present(),
        ]);
    }

    private function presentCollaboratorName(): string
    {
        return $this->notification->collaborator?->full_name?->present() ?: $this->notification->collaboratorFullName->present();
    }

    private function presentFolderName(): string
    {
        return $this->notification->folder?->name?->present() ?: $this->notification->folderName->present();
    }

    private function folderDescriptionChangedNotificationMessage(): string
    {
        return sprintf('%s changed %s description from %s to %s.', ...[
            $this->presentCollaboratorName(),
            $this->presentFolderName(),
            Str::ucfirst($this->notification->changes['from']),
            $this->notification->changes['to'],
        ]);
    }

    private function folderIconChangedNotificationMessage(): string
    {
        return sprintf('%s changed %s icon.', ...[
            $this->presentCollaboratorName(),
            $this->presentFolderName(),
        ]);
    }
}
