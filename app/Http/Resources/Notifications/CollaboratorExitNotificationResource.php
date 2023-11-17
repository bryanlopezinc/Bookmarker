<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\CollaboratorExit;
use Illuminate\Http\Resources\Json\JsonResource;

final class CollaboratorExitNotificationResource extends JsonResource
{
    public function __construct(private CollaboratorExit $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $collaboratorThatLeft = $this->notification->collaborator;
        $folder = $this->notification->folder;

        return [
            'type'       => 'CollaboratorExitNotification',
            'attributes' => [
                'id'                  => $this->notification->uuid,
                'collaborator_exists' => $collaboratorThatLeft !== null,
                'folder_exists'       => $folder !== null,
                'notified_on'         => $this->notification->notifiedOn,
                'collaborator'        => $this->when($collaboratorThatLeft !== null, [
                    'name' => $collaboratorThatLeft?->full_name,
                ]),
                'folder' => $this->when($folder !== null, [
                    'name' => $folder?->name,
                    'id'   => $folder?->id
                ]),
            ]
        ];
    }
}
