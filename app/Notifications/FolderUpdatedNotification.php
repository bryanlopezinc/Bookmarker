<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Folder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class FolderUpdatedNotification extends Notification //implements ShouldQueue
{
    use Queueable;
    use FormatDatabaseNotification;

    public function __construct(private Folder $folder, private int $updatedBy, private string $modified)
    {
        if (!in_array($modified, ['name', 'description'])) {
            throw new \InvalidArgumentException("Invalid modified attribute {$modified}");
        }

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
        $data = [
            'N-type'         => $this->databaseType(),
            'updated_by'     => $this->updatedBy,
            'folder_updated' => $this->folder->id,
            'modified'       => $this->modified,
        ];

        if (!empty($changes = $this->getChanges())) {
            $data['changes'] = $changes;
        }

        return $this->formatNotificationData($data);
    }

    private function getChanges(): array
    {
        if (!in_array($this->modified, ['name', 'description'])) {
            return [];
        }

        return [
            'from' => $this->folder->getOriginal($this->modified),
            'to'   => $this->folder->getDirty()[$this->modified] ?? $this->folder->{$this->modified}
        ];
    }

    public function databaseType(): string
    {
        return NotificationType::FOLDER_UPDATED->value;
    }
}
