<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Activities\CollaboratorRemovedActivityLogData;
use App\DataTransferObjects\Notifications\CollaboratorRemovedNotificationData;
use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class CollaboratorRemovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Folder $folder,
        private User $collaborator,
        private User $removedBy,
        private bool $wasBanned
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
        $notification = new CollaboratorRemovedNotificationData(
            $this->folder,
            $this->wasBanned,
            new CollaboratorRemovedActivityLogData($this->collaborator, $this->removedBy)
        );

        return $notification->toArray();
    }

    public function databaseType(): int
    {
        return NotificationType::COLLABORATOR_REMOVED->value;
    }
}
