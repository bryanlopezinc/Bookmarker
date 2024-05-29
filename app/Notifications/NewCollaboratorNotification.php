<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Notifications\NewCollaboratorNotificationData;
use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class NewCollaboratorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private User $newCollaborator,
        private Folder $folder,
        private User $collaborator
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
        $notification = new NewCollaboratorNotificationData($this->collaborator, $this->folder, $this->newCollaborator);

        return $notification->toArray();
    }

    public function databaseType(): int
    {
        return NotificationType::NEW_COLLABORATOR->value;
    }
}
