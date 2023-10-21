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

    public function __construct(
        private Folder $folder,
        private int $updatedBy
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
            'N-type'         => $this->databaseType(),
            'updated_by'     => $this->updatedBy,
            'folder_updated' => $this->folder->id,
            'changes'        => $this->getChanges()
        ]);
    }

    private function getChanges(): array
    {
        $changes = [
            'name' => [
                'from' => $this->folder->getOriginal('name'),
                'to'   => $this->folder->getDirty()['name'] ?? $this->folder->name
            ],
            'description' => [
                'from' => $this->folder->getOriginal('description'),
                'to'   => $this->folder->getDirty()['description'] ?? $this->folder->description
            ]
        ];

        return collect($changes)
            ->filter(fn (array $change) => $change['from'] !== $change['to'])
            ->whenEmpty(fn () => throw new \Exception('No changes were found for folders', 902))
            ->all();
    }

    public function databaseType(): string
    {
        return NotificationType::FOLDER_UPDATED->value;
    }
}
