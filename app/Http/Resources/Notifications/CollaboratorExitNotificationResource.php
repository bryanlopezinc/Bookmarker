<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Folder;
use App\Contracts\TransformsNotificationInterface;
use App\DataTransferObjects\User;
use App\Repositories\FetchNotificationResourcesRepository as Repository;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

final class CollaboratorExitNotificationResource extends JsonResource implements TransformsNotificationInterface
{
    public function __construct(private DatabaseNotification $notification, private Repository $repository)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $collaboratorThatLeft = $this->getCollaborator();
        $folder = $this->getFolder();

        return [
            'type' => 'CollaboratorExitNotification',
            'attributes' => [
                'id' => $this->notification->id,
                'collaborator_exists' => $collaboratorThatLeft !== null,
                'folder_exists' => $folder !== null,
                'collaborator' => $this->when($collaboratorThatLeft !== null, fn () => [
                    'first_name' => $collaboratorThatLeft->firstName->value,
                    'last_name' => $collaboratorThatLeft->lastName->value
                ]),
                'folder' => $this->when($folder !== null, fn () => [
                    'name' => $folder->name->safe(),
                    'id' => $folder->folderID->value()
                ]),
            ]
        ];
    }

    private function getCollaborator(): ?User
    {
        return $this->repository->findUserByID($this->notification->data['exited_by']);
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
