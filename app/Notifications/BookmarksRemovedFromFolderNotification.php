<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Activities\FolderBookmarksRemovedActivityLogData;
use App\DataTransferObjects\Notifications\BookmarksRemovedFromFolderNotificationData;
use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class BookmarksRemovedFromFolderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private array $bookmarks,
        private Folder $folder,
        private User $collaborator,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * @param mixed $notifiable
     */
    public function toDatabase($notifiable): array
    {
        $notification = new BookmarksRemovedFromFolderNotificationData(
            $this->folder,
            new FolderBookmarksRemovedActivityLogData(collect($this->bookmarks), $this->collaborator)
        );

        return $notification->toArray();
    }

    public function databaseType(): int
    {
        return NotificationType::BOOKMARKS_REMOVED_FROM_FOLDER->value;
    }
}
