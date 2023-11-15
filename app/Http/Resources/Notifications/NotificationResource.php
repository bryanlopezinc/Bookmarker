<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\BookmarksAddedToFolder;
use App\DataTransferObjects\Notifications\BookmarksRemovedFromFolder;
use App\DataTransferObjects\Notifications\CollaboratorExit;
use App\DataTransferObjects\Notifications\FolderUpdated;
use App\DataTransferObjects\Notifications\NewCollaborator;
use Illuminate\Http\Resources\Json\JsonResource;

final class NotificationResource extends JsonResource
{
    public function __construct(private object $notification)
    {
        parent::__construct($notification);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        //phpcs:disable
        return match ($this->notification::class) {
            BookmarksAddedToFolder::class     => (new BookmarksAddedToFolderNotificationResource($this->notification))->toArray($request),
            CollaboratorExit::class           => (new CollaboratorExitNotificationResource($this->notification))->toArray($request),
            BookmarksRemovedFromFolder::class => (new FolderBookmarksRemovedNotificationResource($this->notification))->toArray($request),
            FolderUpdated::class              => (new FolderUpdatedNotificationResource($this->notification))->toArray($request),
            NewCollaborator::class            => (new NewCollaboratorNotificationResource($this->notification))->toArray($request)
        };
    }
}
