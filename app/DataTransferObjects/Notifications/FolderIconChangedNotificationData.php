<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\DataTransferObjects\Activities\FolderIconChangedActivityLogData;
use App\Models\Folder;
use Illuminate\Contracts\Support\Arrayable;

final class FolderIconChangedNotificationData implements Arrayable
{
    public function __construct(
        public readonly Folder $folder,
        public readonly FolderIconChangedActivityLogData $activityLog,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $logData = FolderIconChangedActivityLogData::fromArray($data);

        $folder = new Folder($data['folder']);

        $folder->exists = true;

        return new FolderIconChangedNotificationData($folder, $logData);
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
