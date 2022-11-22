<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Folder;
use App\Contracts\TransformsNotificationInterface;
use App\DataTransferObjects\DatabaseNotification;
use App\DataTransferObjects\User;
use App\Repositories\FetchNotificationResourcesRepository as Repository;
use Illuminate\Http\Resources\Json\JsonResource;

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
                'id' => $this->notification->id->value,
                'collaborator_exists' => $collaborator !== null,
                'folder_exists' => $folder !== null,
                'new_collaborator_exists' => $newCollaborator !== null,
                'collaborator' => $this->when($collaborator !== null, fn () => [
                    'id' => $collaborator->id->value(), // @phpstan-ignore-line
                    'first_name' => $collaborator->firstName->value, // @phpstan-ignore-line
                    'last_name' => $collaborator->lastName->value // @phpstan-ignore-line
                ]),
                'folder' => $this->when($folder !== null, fn () => [
                    'name' => $folder->name->safe(), // @phpstan-ignore-line
                    'id' => $folder->folderID->value() // @phpstan-ignore-line
                ]),
                'new_collaborator' => $this->when($newCollaborator !== null, fn () => [
                    'id' => $newCollaborator->id->value(), // @phpstan-ignore-line
                    'first_name' => $newCollaborator->firstName->value, // @phpstan-ignore-line
                    'last_name' => $newCollaborator->lastName->value // @phpstan-ignore-line
                ]),
            ]
        ];
    }

    /**
     * Get the user that added the collaborator
     */
    private function addedByCollaborator(): ?User
    {
        return $this->repository->findUserByID($this->notification->notificationData['added_by_collaborator']);
    }

    /**
     * Get the user that was added as a collaborator
     */
    private function getNewCollaborator(): ?User
    {
        return $this->repository->findUserByID($this->notification->notificationData['new_collaborator_id']);
    }

    private function getFolder(): ?Folder
    {
        return $this->repository->findFolderByID($this->notification->notificationData['added_to_folder']);
    }

    public function toJsonResource(): JsonResource
    {
        return $this;
    }
}
