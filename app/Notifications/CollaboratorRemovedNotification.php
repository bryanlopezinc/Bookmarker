<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class CollaboratorRemovedNotification extends Notification
{
    use Queueable;
    use FormatDatabaseNotification;

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
        return $this->formatNotificationData([
            'N-type'       => $this->databaseType(),
            'folder'       => [
                'id' => $this->folder->id,
                'public_id' => $this->folder->public_id->value,
                'name' => $this->folder->name->value,
            ],
            'collaborator' => [
                'id' => $this->collaborator->id,
                'name' => $this->collaborator->full_name->value,
                'public_id' => $this->collaborator->public_id->value,
            ],
            'removed_by'   => [
                'id' => $this->removedBy->id,
                'name' => $this->removedBy->full_name->value,
                'public_id' => $this->removedBy->public_id->value,
            ],
            'was_banned'   => $this->wasBanned
        ]);
    }

    public function databaseType(): string
    {
        return NotificationType::COLLABORATOR_REMOVED->value;
    }
}
