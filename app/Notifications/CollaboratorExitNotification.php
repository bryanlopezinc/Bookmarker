<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Activities\CollaboratorExitActivityLogData;
use App\DataTransferObjects\Notifications\CollaboratorExitNotificationData;
use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class CollaboratorExitNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Folder $folder, private User $collaborator)
    {
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
        $notification = new CollaboratorExitNotificationData($this->folder, new CollaboratorExitActivityLogData($this->collaborator));

        return $notification->toArray();
    }

    public function databaseType(): int
    {
        return NotificationType::COLLABORATOR_EXIT->value;
    }
}
