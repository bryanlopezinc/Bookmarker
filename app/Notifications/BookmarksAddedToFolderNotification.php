<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Notifications\NewFolderBookmarksNotificationData;
use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class BookmarksAddedToFolderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private array $bookmarks,
        private Folder $folder,
        private User $user,
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
        $notification = new NewFolderBookmarksNotificationData($this->folder, $this->user, $this->bookmarks);

        return $notification->toArray();
    }

    public function databaseType(): int
    {
        return NotificationType::BOOKMARKS_ADDED_TO_FOLDER->value;
    }
}
