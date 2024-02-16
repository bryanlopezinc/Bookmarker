<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications;
use Illuminate\Http\Resources\Json\JsonResource;

final class NotificationResource extends JsonResource
{
    public function __construct(private object $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        /** @var mixed */
        $notification = $this->notification;

        return match ($type = $notification::class) {
            Notifications\NewFolderBookmarksNotificationData::class => (new BookmarksAddedToFolderNotificationResource($notification))->toArray($request),
            Notifications\CollaboratorExitNotificationData::class => (new CollaboratorExitNotificationResource($notification))->toArray($request),
            Notifications\BookmarksRemovedFromFolderNotificationData::class => (new FolderBookmarksRemovedNotificationResource($notification))->toArray($request),
            Notifications\FolderUpdatedNotificationData::class => (new FolderUpdatedNotificationResource($notification))->toArray($request),
            Notifications\NewCollaboratorNotificationData::class => (new NewCollaboratorNotificationResource($notification))->toArray($request),
            Notifications\YouHaveBeenKickedOutNotificationData::class => (new YouHaveBeenBootedOutNotificationResource($notification))->toArray($request),
            Notifications\ImportFailedNotificationData::class => new ImportFailedNotificationResource($notification),
            default => throw new \RuntimeException("Invalid notification type $type")
        };
    }
}
