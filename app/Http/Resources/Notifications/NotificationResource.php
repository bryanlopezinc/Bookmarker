<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\Enums\NotificationType as Type;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Importing\Http\Resources\ImportFailedNotificationResource;
use App\Models\DatabaseNotification;

final class NotificationResource extends JsonResource
{
    public function __construct(private DatabaseNotification $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return match ($this->notification->type) {
            Type::BOOKMARKS_ADDED_TO_FOLDER     => new NewFolderBookmarksResource($this->notification),
            Type::BOOKMARKS_REMOVED_FROM_FOLDER => new FolderBookmarksRemovedResource($this->notification),
            Type::NEW_COLLABORATOR              => new NewCollaboratorResource($this->notification),
            Type::COLLABORATOR_EXIT             => new CollaboratorExitResource($this->notification),
            Type::COLLABORATOR_REMOVED          => new CollaboratorRemovedResource($this->notification),
            Type::FOLDER_NAME_UPDATED           => new FolderNameChangedResource($this->notification),
            Type::FOLDER_DESCRIPTION_UPDATED    => new FolderDescriptionChangedResource($this->notification),
            Type::FOLDER_ICON_UPDATED           => new FolderIconChangedResource($this->notification),
            Type::YOU_HAVE_BEEN_KICKED_OUT      => new YouHaveBeenBootedOutResource($this->notification),
            default => new ImportFailedNotificationResource($this->notification),
        };
    }
}
