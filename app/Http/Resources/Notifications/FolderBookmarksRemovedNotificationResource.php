<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\BookmarksRemovedFromFolder;
use App\Models\Bookmark;
use Illuminate\Http\Resources\Json\JsonResource;

final class FolderBookmarksRemovedNotificationResource extends JsonResource
{
    public function __construct(private BookmarksRemovedFromFolder $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $collaborator = $this->notification->collaborator;
        $folder = $this->notification->folder;
        $bookmarks = $this->notification->bookmarks;

        return [
            'type' => 'BookmarksRemovedFromFolderNotification',
            'attributes' => [
                'id'                  => $this->notification->uuid,
                'collaborator_exists' => $collaborator !== null,
                'folder_exists'       => $folder !== null,
                'bookmarks_count'     => count($bookmarks),
                'notified_on'         => $this->notification->notifiedOn,
                'by_collaborator'     => $this->when($collaborator !== null, fn () => [
                    'id'   => $collaborator->id,
                    'name' => $collaborator->full_name,
                ]),
                'folder'              => $this->when($folder !== null, fn () => [
                    'name' => $folder->name,
                    'id'   => $folder->id
                ]),
                'bookmarks' => array_map(fn (Bookmark $bookmark) => [
                    'title' => $bookmark->title
                ], $bookmarks)
            ]
        ];
    }
}
