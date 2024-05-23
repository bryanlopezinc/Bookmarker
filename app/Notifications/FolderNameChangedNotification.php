<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Notifications\FolderNameChangedNotificationData;
use App\Enums\NotificationType;
use App\DataTransferObjects\Activities\FolderNameChangedActivityLogData;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class FolderNameChangedNotification extends Notification
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
        $notification = new FolderNameChangedNotificationData(
            $this->folder,
            new FolderNameChangedActivityLogData(
                $this->collaborator,
                $this->folder->getOriginal('name')->value,
                $this->folder->name->value,
            )
        );

        return $notification->toArray();
    }

    public function databaseType(): int
    {
        return NotificationType::FOLDER_NAME_UPDATED->value;
    }
}
