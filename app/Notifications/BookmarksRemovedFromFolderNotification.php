<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class BookmarksRemovedFromFolderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatDatabaseNotification;

    public function __construct(
        private array $bookmarkIDs,
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
        return $this->formatNotificationData([
            'N-type' => $this->databaseType(),
            'bookmark_ids'    => $this->bookmarkIDs,
            'folder'          => [
                'id'        => $this->folder->id,
                'public_id' => $this->folder->public_id->value,
                'name'      => $this->folder->name->value,
            ],
            'collaborator'          => [
                'id'        => $this->collaborator->id,
                'public_id' => $this->collaborator->public_id->value,
                'name'      => $this->collaborator->full_name->value,
            ],
        ]);
    }

    public function databaseType(): string
    {
        return NotificationType::BOOKMARKS_REMOVED_FROM_FOLDER->value;
    }
}
