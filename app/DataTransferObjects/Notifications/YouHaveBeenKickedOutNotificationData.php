<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\Models\Folder;
use Illuminate\Contracts\Support\Arrayable;

final class YouHaveBeenKickedOutNotificationData implements Arrayable
{
    public function __construct(public readonly Folder $folder)
    {
    }

    public static function fromArray(array $data): self
    {
        $folder = new Folder($data['folder']);

        $folder->exists = true;

        return new YouHaveBeenKickedOutNotificationData($folder);
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        return [
            'version' => '1.0.0',
            'folder'  => $this->folder->activityLogContextVariables()
        ];
    }
}
