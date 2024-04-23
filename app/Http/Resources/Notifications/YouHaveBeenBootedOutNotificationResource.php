<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\YouHaveBeenKickedOutNotificationData;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

final class YouHaveBeenBootedOutNotificationResource extends JsonResource
{
    public function __construct(private YouHaveBeenKickedOutNotificationData $notification)
    {
    }

    #[Override]
    public function toArray($request)
    {
        return [
            'type' => 'YouHaveBeenKickedOutNotification',
            'attributes' => [
                'id'            => $this->notification->uuid,
                'notified_on'    => $this->notification->notifiedOn,
                'folder_exists' => $this->notification->folder !== null,
                'message'       => $this->notificationMessage(),
                'folder_id'     => $this->notification->folderId->present()
            ]
        ];
    }

    private function notificationMessage(): string
    {
        return sprintf('You were removed from %s folder.', ...[
            $this->notification->folder?->name?->present() ?: $this->notification->folderName->present()
        ]);
    }
}
