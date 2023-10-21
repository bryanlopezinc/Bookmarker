<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class NewCollaboratorNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatDatabaseNotification;

    public function __construct(
        private int $newCollaboratorID,
        private int $folderID,
        private int $addedByCollaboratorID
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
        return $this->formatNotificationData([
            'N-type'                => $this->databaseType(),
            'added_by_collaborator' => $this->addedByCollaboratorID,
            'added_to_folder'       => $this->folderID,
            'new_collaborator_id'   => $this->newCollaboratorID
        ]);
    }

    public function databaseType(): string
    {
        return NotificationType::NEW_COLLABORATOR->value;
    }
}
