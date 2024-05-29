<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\DataTransferObjects\Activities\CollaboratorRemovedActivityLogData as ActivityLogData;
use App\Models\Folder;
use Illuminate\Contracts\Support\Arrayable;

final class CollaboratorRemovedNotificationData implements Arrayable
{
    public function __construct(
        public readonly Folder $folder,
        public readonly bool $wasBanned,
        public readonly ActivityLogData $activityLog
    ) {
    }

    public static function fromArray(array $data): self
    {
        $folder = new Folder($data['folder']);

        $folder->exists = true;

        return new CollaboratorRemovedNotificationData(
            $folder,
            $data['banned'],
            ActivityLogData::fromArray($data)
        );
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        return array_replace($this->activityLog->toArray(), [
            'version' => '1.0.0',
            'folder'  => $this->folder->activityLogContextVariables(),
            'banned'  => $this->wasBanned
        ]);
    }
}
