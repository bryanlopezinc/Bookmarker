<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Notifications\FolderIconChangedNotificationData;
use App\Enums\NotificationType;
use App\DataTransferObjects\Activities\FolderIconChangedActivityLogData;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class FolderIconUpdatedNotification extends Notification
{
    public function __construct(private Folder $folder, private User $collaborator)
    {
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
        $notification = new FolderIconChangedNotificationData(
            $this->folder,
            new FolderIconChangedActivityLogData($this->collaborator)
        );

        return $notification->toArray();
    }

    public function databaseType(): int
    {
        return NotificationType::FOLDER_ICON_UPDATED->value;
    }
}
