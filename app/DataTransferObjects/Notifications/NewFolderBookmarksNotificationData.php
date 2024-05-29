<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\DataTransferObjects\Activities\NewFolderBookmarksActivityLogData as ActivityLogData;
use App\Models\Folder;
use App\Models\User;
use App\Models\Bookmark;
use Illuminate\Contracts\Support\Arrayable;

final class NewFolderBookmarksNotificationData implements Arrayable
{
    /**
     * @param array<Bookmark> $bookmarks
     */
    public function __construct(
        public readonly Folder $folder,
        public readonly User $collaborator,
        public readonly array $bookmarks,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $logData = ActivityLogData::fromArray($data);
        $folder = new Folder($data['folder']);

        $folder->exists = true;

        return new NewFolderBookmarksNotificationData($folder, $logData->collaborator, $logData->bookmarks->all());
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        $logData = (new ActivityLogData(collect($this->bookmarks), $this->collaborator))->toArray();

        return array_replace($logData, [
            'version' => '1.0.0',
            'folder'  => $this->folder->activityLogContextVariables()
        ]);
    }
}
