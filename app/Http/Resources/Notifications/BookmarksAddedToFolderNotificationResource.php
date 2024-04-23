<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DataTransferObjects\Notifications\NewFolderBookmarksNotificationData;

final class BookmarksAddedToFolderNotificationResource extends JsonResource
{
    public function __construct(private NewFolderBookmarksNotificationData $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'type' => 'BookmarksAddedToFolderNotification',
            'attributes' => [
                'id'                  => $this->notification->notificationId,
                'collaborator_exists' => $this->notification->collaborator !== null,
                'folder_exists'       => $this->notification->folder !== null,
                'message'             => $this->notificationMessage(),
                'notified_on'          => $this->notification->notifiedOn,
                'collaborator_id'     =>  $this->notification->collaboratorId->present(),
                'folder_id'           => $this->notification->folderId->present(),
            ]
        ];
    }

    private function notificationMessage(): string
    {
        $bookmarksCount = count($this->notification->bookmarks);

        return sprintf('%s added %s %s to %s folder.', ...[
            $this->notification->collaborator?->full_name?->present() ?: $this->notification->collaboratorFullName->present(),
            $bookmarksCount,
            $bookmarksCount > 1 ? 'bookmarks' : 'bookmark',
            $this->notification->folder?->name?->present() ?: $this->notification->folderName->present()
        ]);
    }
}
