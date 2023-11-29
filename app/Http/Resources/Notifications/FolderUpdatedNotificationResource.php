<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\FolderUpdated;
use App\Filesystem\ProfileImageFileSystem;
use Illuminate\Http\Resources\Json\JsonResource;

final class FolderUpdatedNotificationResource extends JsonResource
{
    public function __construct(private FolderUpdated $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $updatedBy = $this->notification->collaborator;

        $folder = $this->notification->folder;

        return [
            'type'       => 'FolderUpdatedNotification',
            'attributes' => [
                'modified'            => $this->notification->modifiedAttribute,
                'changes'             => $this->notification->changes,
                'id'                  => $this->notification->uuid,
                'collaborator_exists' => $updatedBy !== null,
                'folder_exists'       => $folder !== null,
                'notified_on'         => $this->notification->notifiedOn,
                'collaborator'        => $this->when($updatedBy !== null, [
                    'id'   => $updatedBy?->id,
                    'name' => $updatedBy?->full_name,
                    'profile_image_url' => (new ProfileImageFileSystem())->publicUrl($updatedBy?->profile_image_path)
                ]),
                'folder'              => $this->when($folder !== null, [
                    'name' => $folder?->name,
                    'id'   => $folder?->id
                ]),
            ]
        ];
    }
}
