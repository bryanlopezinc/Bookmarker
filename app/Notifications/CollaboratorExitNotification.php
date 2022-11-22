<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class CollaboratorExitNotification extends Notification implements ShouldQueue
{
    use Queueable, FormatDatabaseNotification;

    public function __construct(private ResourceID $folderID, private UserID $collaboratorThatLeft)
    {
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
            'N-type' => $this->databaseType(),
            'exited_from_folder' => $this->folderID->value(),
            'exited_by' => $this->collaboratorThatLeft->value()
        ]);
    }

    public function databaseType(): string
    {
        return NotificationType::COLLABORATOR_EXIT->value;
    }
}
