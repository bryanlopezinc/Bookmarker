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
            'N-type'                 => $this->databaseType(),
            'collaborator_id'        => $this->collaborator->id,
            'collaborator_full_name' => $this->collaborator->full_name->value,
            'folder_id'              => $this->folder->id,
            'folder_name'            => $this->folder->name->value,
            'new_collaborator_id'    => $this->newCollaborator->id,
            'new_collaborator_full_name' => $this->newCollaborator->full_name->value
        ]);
    }

    public function databaseType(): string
    {
        return NotificationType::NEW_COLLABORATOR->value;
    }
}
