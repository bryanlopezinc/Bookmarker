<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Notifications\YouHaveBeenKickedOutNotificationData;
use App\Enums\NotificationType;
use App\Models\Folder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class YouHaveBeenBootedOutNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Folder $folder)
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
        return (new YouHaveBeenKickedOutNotificationData($this->folder))->toArray();
    }

    public function databaseType(): int
    {
        return NotificationType::YOU_HAVE_BEEN_KICKED_OUT->value;
    }
}
