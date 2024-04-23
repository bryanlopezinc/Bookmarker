<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;
use InvalidArgumentException;

abstract class AbstractFolderUpdatedNotification extends Notification //implements ShouldQueue
{
    use Queueable;
    use FormatDatabaseNotification;

    protected Folder $folder;
    protected User $updatedBy;
    protected string $modifiedAttribute;

    public function __construct(Folder $folder, User $updatedBy, string $modifiedAttribute)
    {
        if ( ! in_array($modifiedAttribute, ['name', 'description', 'icon_path'])) {
            throw new InvalidArgumentException("Invalid modified attribute {$modifiedAttribute}"); // @codeCoverageIgnore
        }

        $this->folder = $folder;
        $this->updatedBy = $updatedBy;
        $this->modifiedAttribute = $modifiedAttribute;

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
        $data = [
            'N-type'  => $this->databaseType(),
            'modified' => $this->modifiedAttribute,
            'folder'  => [
                'id'        => $this->folder->id,
                'public_id' => $this->folder->public_id->value,
                'name'      => $this->folder->getOriginal('name')->value
            ],
            'collaborator' => [
                'id'        => $this->updatedBy->id,
                'full_name' => $this->updatedBy->full_name->value,
                'public_id' => $this->updatedBy->public_id->value
            ],
        ];

        if ( ! empty($changes = $this->getChanges())) {
            $data['changes'] = $changes;
        }

        return $this->formatNotificationData($data);
    }

    protected function getChanges(): array
    {
        return [
            'from' => $this->folder->getOriginal($this->modifiedAttribute),
            'to'   => $this->folder->getDirty()[$this->modifiedAttribute] ?? $this->folder->{$this->modifiedAttribute}
        ];
    }

    final public function databaseType(): string
    {
        return NotificationType::FOLDER_UPDATED->value;
    }
}
