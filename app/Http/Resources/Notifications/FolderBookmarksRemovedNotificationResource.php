<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DataTransferObjects\Notifications\BookmarksRemovedFromFolderNotificationData;

final class FolderBookmarksRemovedNotificationResource extends JsonResource
{
    public function __construct(private BookmarksRemovedFromFolderNotificationData $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return [
            'type' => 'BookmarksRemovedFromFolderNotification',
            'attributes' => [
                'id'                  => $this->notification->id,
                'collaborator_exists' => $this->notification->collaborator !== null,
                'folder_exists'       => $this->notification->folder !== null,
                'message'             => $this->notificationMessage(),
                'notified_on'          => $this->notification->notifiedOn,
                'collaborator_id'     =>  $this->notification->collaboratorId,
                'folder_id'           => $this->notification->folderId,
            ]
        ];
    }

    private function notificationMessage(): string
    {
        $bookmarksCount = count($this->notification->bookmarks);

        return sprintf('%s removed %s %s from %s folder.', ...[
            $this->notification->collaborator?->full_name?->present() ?: $this->notification->collaboratorFullName->present(),
            $bookmarksCount,
            $bookmarksCount > 1 ? 'bookmarks' : 'bookmark',
            $this->notification->folder?->name?->present() ?: $this->notification->folderName->present()
        ]);
    }
}
