<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\FolderUpdated;
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
                'changes'             => $this->notification->changes,
                'id'                  => $this->notification->uuid,
                'collaborator_exists' => $updatedBy !== null,
                'folder_exists'       => $folder !== null,
                'collaborator'        => $this->when($updatedBy !== null, fn () => [
                    'id'         => $updatedBy->id,
                    'first_name' => $updatedBy->first_name,
                    'last_name'  => $updatedBy->last_name
                ]),
                'folder'              => $this->when($folder !== null, fn () => [
                    'name' => $folder->name,
                    'id'   => $folder->id
                ]),
            ]
        ];
    }
}
