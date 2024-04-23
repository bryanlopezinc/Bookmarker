<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class NewCollaboratorNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatDatabaseNotification;

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
        return $this->formatNotificationData([
            'N-type'                 => $this->databaseType(),
            'folder'          => [
                'id'        => $this->folder->id,
                'public_id' => $this->folder->public_id->value,
                'name'      => $this->folder->name->value
            ],
            'collaborator' => [
                'id'        => $this->collaborator->id,
                'full_name' => $this->collaborator->full_name->value,
                'public_id' => $this->collaborator->public_id->value
            ],
            'new_collaborator' => [
                'id'        => $this->newCollaborator->id,
                'full_name' => $this->newCollaborator->full_name->value,
                'public_id' => $this->newCollaborator->public_id->value
            ]
        ]);
    }

    public function databaseType(): string
    {
        return NotificationType::NEW_COLLABORATOR->value;
    }
}
