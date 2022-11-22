<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\Folder;
use App\Enums\NotificationType;
use App\ValueObjects\UserID;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class FolderUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable, FormatDatabaseNotification;

    public function __construct(private Folder $original, private Folder $updated, private UserID $updatedBy)
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
            'updated_by' => $this->updatedBy->value(),
            'folder_updated' => $this->original->folderID->value(),
            'changes' => $this->getChanges()
        ]);
    }

    private function getChanges(): array
    {
        $initialTags = $this->original->tags->toStringCollection();
        $updatedTags = $this->updated->tags->toStringCollection();

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
                'from' => $initialTags->implode(','),
                'to' => $initialTags == $updatedTags ? $initialTags->implode(',') : $initialTags->merge($updatedTags)->implode(',')
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
