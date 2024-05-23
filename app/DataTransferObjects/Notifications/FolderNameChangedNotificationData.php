<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\DataTransferObjects\Activities\FolderNameChangedActivityLogData;
use App\Models\Folder;
use Illuminate\Contracts\Support\Arrayable;

final class FolderNameChangedNotificationData implements Arrayable
{
    public function __construct(
        public readonly Folder $folder,
        public readonly FolderNameChangedActivityLogData $activityLog
    ) {
    }

    public static function fromArray(array $data): self
    {
        $folder = new Folder($data['folder']);

        $folder->exists = true;

        return new FolderNameChangedNotificationData($folder, FolderNameChangedActivityLogData::fromArray($data));
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        return array_replace($this->activityLog->toArray(), [
            'version' => '1.0.0',
            'folder'  => $this->folder->activityLogContextVariables(),
        ]);
    }
}
