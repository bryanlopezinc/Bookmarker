<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class BookmarksAddedToFolderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatDatabaseNotification;

    public function __construct(
        private array $bookmarkIds,
        private Folder $folder,
        private User $user,
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
            'N-type'          => $this->databaseType(),
            'bookmark_ids'    => $this->bookmarkIds,
            'folder_id'       => $this->folder->id,
            'collaborator_id' => $this->user->id,
            'full_name'       => $this->user->full_name->value,
            'folder_name'     => $this->folder->name->value
        ]);
    }

    public function databaseType(): string
    {
        return NotificationType::BOOKMARKS_ADDED_TO_FOLDER->value;
    }
}
