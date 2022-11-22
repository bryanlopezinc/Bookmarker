<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Collections\ResourceIDsCollection;
use App\Enums\NotificationType;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class BookmarksAddedToFolderNotification extends Notification implements ShouldQueue
{
    use Queueable, FormatDatabaseNotification;

    public function __construct(private ResourceIDsCollection $bookmarks, private ResourceID $folderID, private UserID $collaboratorID)
    {
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
     * @param  mixed  $notifiable
     */
    public function toDatabase($notifiable): array
    {
        return $this->formatNotificationData([
            'N-type' => $this->databaseType(),
            'bookmarks_added_to_folder' => $this->bookmarks->asIntegers()->all(),
            'added_to_folder' => $this->folderID->value(),
            'added_by' => $this->collaboratorID->value()
        ]);
    }

    public function databaseType(): string
    {
        return NotificationType::BOOKMARKS_ADDED_TO_FOLDER->value;
    }
}
