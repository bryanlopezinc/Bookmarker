<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class CollaboratorExitNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatDatabaseNotification;

    public function __construct(private Folder $folder, private User $collaboratorThatLeft)
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
        return $this->formatNotificationData([
            'N-type'          => $this->databaseType(),
            'folder'          => [
                'id'        => $this->folder->id,
                'public_id' => $this->folder->public_id->value,
                'name'      => $this->folder->name->value
            ],
            'collaborator' => [
                'id'        => $this->collaboratorThatLeft->id,
                'full_name' => $this->collaboratorThatLeft->full_name->value,
                'public_id' => $this->collaboratorThatLeft->public_id->value
            ]
        ]);
    }

    public function databaseType(): string
    {
        return NotificationType::COLLABORATOR_EXIT->value;
    }
}
