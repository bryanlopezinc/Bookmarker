<?php

declare(strict_types=1);

namespace App\Importing\Notifications;

use App\Enums\NotificationType;
use App\Importing\ImportBookmarksOutcome;
use App\Importing\Models\Import;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class ImportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Import $import, private ImportBookmarksOutcome $result)
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
        return [
            'version'   => '1.0.0',
            'import_id' => $this->import->id,
            'public_id' => $this->import->public_id->value,
            'reason'    => $this->result->status->value,
        ];
    }

    public function databaseType(): int
    {
        return NotificationType::IMPORT_FAILED->value;
    }
}
