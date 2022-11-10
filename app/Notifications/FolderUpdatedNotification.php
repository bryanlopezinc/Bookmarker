<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Folder;
use App\ValueObjects\UserID;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class FolderUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'FolderUpdated';

    public function __construct(
        private Folder $original,
        private Folder $updated,
        private UserID $updatedBy
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
        return [
            'updated_by' => $this->updatedBy->value(),
            'folder_id' => $this->original->folderID->value(),
            'changes' => $this->getChanges()
        ];
    }

    private function getChanges(): array
    {
        $changes = [
            'name' => [
                'from' => $this->original->name->safe(),
                'to' => $this->updated->name->safe()
            ],
            'description' => [
                'from' => $this->original->description->safe(),
                'to' => $this->updated->description->safe()
            ],
            'tags' => [
                'from' => $this->original->tags->toStringCollection()->implode(','),
                'to' => $this->original->tags->toStringCollection()->merge($this->updated->tags->toStringCollection())->implode(','),
            ]
        ];

        return array_filter($changes, function (array $change) {
            return $change['from'] !== $change['to'];
        });
    }

    public function databaseType(): string
    {
        return self::TYPE;
    }
}
