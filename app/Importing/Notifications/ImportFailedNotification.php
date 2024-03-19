<?php

declare(strict_types=1);

namespace App\Importing\Notifications;

use App\Enums\NotificationType;
use App\Importing\ImportBookmarksOutcome;
use App\Notifications\FormatDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class ImportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatDatabaseNotification;

    public function __construct(private string $importId, private ImportBookmarksOutcome $result)
    {
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
            'N-type'    => $this->databaseType(),
            'import_id' => $this->importId,
            'reason'    => $this->result->status->value,
        ]);
    }

    public function databaseType(): string
    {
        return NotificationType::IMPORT_FAILED->value;
    }
}
