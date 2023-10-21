<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\NewCollaborator;
use Illuminate\Http\Resources\Json\JsonResource;

final class NewCollaboratorNotificationResource extends JsonResource
{
    public function __construct(private NewCollaborator $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $newCollaborator = $this->notification->newCollaborator;
        $folder = $this->notification->folder;
        $collaborator = $this->notification->collaborator;

        return [
            'type'       => 'CollaboratorAddedToFolderNotification',
            'attributes' => [
                'id'                      => $this->notification->uuid,
                'collaborator_exists'     => $collaborator !== null,
                'folder_exists'           => $folder !== null,
                'new_collaborator_exists' => $newCollaborator !== null,
                'collaborator'            => $this->when($collaborator !== null, fn () => [
                    'id'         => $collaborator->id,
                    'first_name' => $collaborator->first_name,
                    'last_name'  => $collaborator->last_name
                ]),
                'folder'  => $this->when($folder !== null, fn () => [
                    'name' => $folder->name,
                    'id'  => $folder->id
                ]),
                'new_collaborator' => $this->when($newCollaborator !== null, fn () => [
                    'id'         => $newCollaborator->id,
                    'first_name' => $newCollaborator->first_name,
                    'last_name'  => $newCollaborator->last_name
                ]),
            ]
        ];
    }
}
