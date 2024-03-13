<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use App\ValueObjects\FolderName;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

final class FolderUpdatedNotification extends Notification //implements ShouldQueue
{
    use Queueable;
    use FormatDatabaseNotification;

    public function __construct(private Folder $folder, private User $updatedBy, private string $modifiedAttributeName)
    {
        if (!in_array($modifiedAttributeName, ['name', 'description'])) {
            throw new \InvalidArgumentException("Invalid modified attribute {$modifiedAttributeName}"); // @codeCoverageIgnore
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
            'N-type'                 => $this->databaseType(),
            'collaborator_id'        => $this->updatedBy->id,
            'collaborator_full_name' => $this->updatedBy->full_name->value,
            'folder_id'              => $this->folder->id,
            'folder_name'            => $this->folder->getOriginal('name')->value,
            'modified'                => $this->modifiedAttributeName,
        ];

        if (!empty($changes = $this->getChanges())) {
            $data['changes'] = $changes;
        }

        return $this->formatNotificationData($data);
    }

    private function getChanges(): array
    {
        $from = $this->folder->getOriginal($this->modifiedAttributeName);
        $to = $this->folder->getDirty()[$this->modifiedAttributeName] ?? $this->folder->{$this->modifiedAttributeName};

        if ($from instanceof FolderName) {
            $from = $from->value;
        }

        if ($to instanceof FolderName) {
            $to = $to->value;
        }

        return [
            'from' => $from,
            'to'   => $to
        ];
    }

    public function databaseType(): string
    {
        return NotificationType::FOLDER_UPDATED->value;
    }
}
