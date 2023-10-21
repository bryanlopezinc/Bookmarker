<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class BookmarksAddedToFolderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatDatabaseNotification;

    public function __construct(
        private array $bookmarkIds,
        private int $folderId,
        private int $userId
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
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
        return $this->formatNotificationData([
            'N-type'                    => $this->databaseType(),
            'bookmarks_added_to_folder' => $this->bookmarkIds,
            'added_to_folder'           => $this->folderId,
            'added_by'                  => $this->userId
        ]);
    }

    public function databaseType(): string
    {
        return NotificationType::BOOKMARKS_ADDED_TO_FOLDER->value;
    }
}
