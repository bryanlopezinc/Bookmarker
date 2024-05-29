<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\YouHaveBeenKickedOutNotificationData;
use App\Models\DatabaseNotification;
use App\Models\Folder;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

final class YouHaveBeenBootedOutResource extends JsonResource
{
    public function __construct(private DatabaseNotification $notification)
    {
    }

    #[Override]
    public function toArray($request)
    {
        $data = YouHaveBeenKickedOutNotificationData::fromArray($this->notification->data);

        $folder = $this->notification->resources->findFolderById($data->folder->id);

        return [
            'type' => 'YouHaveBeenKickedOutNotification',
            'attributes' => [
                'id'         => $this->notification->id,
                'notified_on' => $this->notification->created_at,
                'folder'     => new FolderResource($data->folder, $folder),
                'message'    => $this->notificationMessage($folder),
            ]
        ];
    }

    private function notificationMessage(Folder $folder): string
    {
        $data = YouHaveBeenKickedOutNotificationData::fromArray($this->notification->data);

        return sprintf('You were removed from %s folder', ...[
            $folder->getNameOr($data->folder)->present()
        ]);
    }
}
