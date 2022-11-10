<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Folder;
use App\Contracts\TransformsNotificationInterface;
use App\DataTransferObjects\User;
use App\Repositories\FetchNotificationResourcesRepository as Repository;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

final class NewCollaboratorNotificationResource extends JsonResource implements TransformsNotificationInterface
{
    public function __construct(private DatabaseNotification $notification, private Repository $repository)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $newCollaborator = $this->getNewCollaborator();
        $folder = $this->getFolder();
        $collaborator = $this->addedByCollaborator();

        return [
            'type' => 'CollaboratorAddedToFolderNotification',
            'attributes' => [
                'id' => $this->notification->id,
                'collaborator_exists' => $collaborator !== null,
                'folder_exists' => $folder !== null,
                'new_collaborator_exists' => $newCollaborator !== null,
                'collaborator' => $this->when($collaborator !== null, fn () => [
                    'id' => $collaborator->id->value(),
                    'first_name' => $collaborator->firstName->value,
                    'last_name' => $collaborator->lastName->value
                ]),
                'folder' => $this->when($folder !== null, fn () => [
                    'name' => $folder->name->safe(),
                    'id' => $folder->folderID->value()
                ]),
                'new_collaborator' => $this->when($newCollaborator !== null, fn () => [
                    'id' => $newCollaborator->id->value(),
                    'first_name' => $newCollaborator->firstName->value,
                    'last_name' => $newCollaborator->lastName->value
                ]),
            ]
        ];
    }

    /**
     * Get the user that added the collaborator
     */
    private function addedByCollaborator(): ?User
    {
        return $this->repository->findUserByID($this->notification->data['added_by']);
    }

    /**
     * Get the user that was added as a collaborator
     */
    private function getNewCollaborator(): ?User
    {
        return $this->repository->findUserByID($this->notification->data['new_collaborator_id']);
    }

    private function getFolder(): ?Folder
    {
        return $this->repository->findFolderByID($this->notification->data['folder_id']);
    }

    public function toJsonResource(): JsonResource
    {
        return $this;
    }
}
