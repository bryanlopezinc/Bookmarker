<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class BookmarksRemovedFromFolderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const DATABASE_TYPE = 'bookmarksRemovedFromFolder';

    public function __construct(
        private ResourceIDsCollection $bookmarkIDs,
        private ResourceID $folderID,
        private UserID $collaboratorID
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
     * @param  mixed  $notifiable
     */
    public function toDatabase($notifiable): array
    {
        return [
            'bookmarks' => $this->bookmarkIDs->asIntegers()->all(),
            'folder_id' => $this->folderID->value(),
            'removed_by' => $this->collaboratorID->value()
        ];
    }

    public function databaseType(): string
    {
        return self::DATABASE_TYPE;
    }
}
