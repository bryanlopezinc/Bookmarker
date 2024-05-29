<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\DataTransferObjects\Activities\DescriptionChangedActivityLogData;
use App\Models\Folder;
use Illuminate\Contracts\Support\Arrayable;

final class FolderDescriptionChangedNotificationData implements Arrayable
{
    public function __construct(
        public readonly Folder $folder,
        public readonly DescriptionChangedActivityLogData $activityLog
    ) {
    }

    public static function fromArray(array $data): self
    {
        $logData = DescriptionChangedActivityLogData::fromArray($data);

        $folder = new Folder($data['folder']);

        $folder->exists = true;

        return new FolderDescriptionChangedNotificationData($folder, $logData);
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
