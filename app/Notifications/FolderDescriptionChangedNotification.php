<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Notifications\FolderDescriptionChangedNotificationData;
use App\Enums\NotificationType;
use App\DataTransferObjects\Activities\DescriptionChangedActivityLogData;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class FolderDescriptionChangedNotification extends Notification
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
        $notification = new FolderDescriptionChangedNotificationData(
            $this->folder,
            new DescriptionChangedActivityLogData(
                $this->collaborator,
                $this->folder->getOriginal('description'),
                $this->folder->description,
            )
        );

        return $notification->toArray();
    }

    public function databaseType(): int
    {
        return NotificationType::FOLDER_DESCRIPTION_UPDATED->value;
    }
}
