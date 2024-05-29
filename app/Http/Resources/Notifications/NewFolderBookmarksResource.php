<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\Models\User;
use App\Models\Folder;
use Illuminate\Support\Str;
use App\Models\DatabaseNotification;
use Illuminate\Http\Resources\Json\JsonResource;
use App\DataTransferObjects\Notifications\NewFolderBookmarksNotificationData;

final class NewFolderBookmarksResource extends JsonResource
{
    public function __construct(private DatabaseNotification $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $data = NewFolderBookmarksNotificationData::fromArray($this->notification->data);

        $collaborator = $this->notification->resources->findUserById($data->collaborator->id);

        $folder = $this->notification->resources->findFolderById($data->folder->id);

        return [
            'type' => 'BookmarksAddedToFolderNotification',
            'attributes' => [
                'id'           => $this->notification->id,
                'collaborator' => new UserResource($data->collaborator, $collaborator),
                'folder'       => new FolderResource($data->folder, $folder),
                'message'      => $this->notificationMessage($collaborator, $folder),
                'notified_on'   => $this->notification->created_at,
            ]
        ];
    }

    private function notificationMessage(User $collaborator, Folder $folder): string
    {
        $data = NewFolderBookmarksNotificationData::fromArray($this->notification->data);

        $bookmarksCount = count($data->bookmarks);

        return sprintf('%s added %s %s to %s folder.', ...[
            $collaborator->getFullNameOr($data->collaborator)->present(),
            $bookmarksCount,
            Str::plural('bookmark', $bookmarksCount),
            $folder->getNameOr($data->folder)->present()
        ]);
    }
}
