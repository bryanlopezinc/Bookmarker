<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Folder;
use App\Contracts\TransformsNotificationInterface;
use App\DataTransferObjects\DatabaseNotification;
use App\DataTransferObjects\User;
use App\Repositories\FetchNotificationResourcesRepository as Repository;
use Illuminate\Http\Resources\Json\JsonResource;

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
                'id' => $this->notification->id->value,
                'collaborator_exists' => $collaboratorThatLeft !== null,
                'folder_exists' => $folder !== null,
                'collaborator' => $this->when($collaboratorThatLeft !== null, fn () => [
                    'first_name' => $collaboratorThatLeft->firstName->value, // @phpstan-ignore-line
                    'last_name' => $collaboratorThatLeft->lastName->value // @phpstan-ignore-line
                ]),
                'folder' => $this->when($folder !== null, fn () => [
                    'name' => $folder->name->safe(), // @phpstan-ignore-line
                    'id' => $folder->folderID->value() // @phpstan-ignore-line
                ]),
            ]
        ];
    }

    private function getCollaborator(): ?User
    {
        return $this->repository->findUserByID($this->notification->notificationData['exited_by']);
    }

    private function getFolder(): ?Folder
    {
        return $this->repository->findFolderByID($this->notification->notificationData['exited_from_folder']);
    }

    public function toJsonResource(): JsonResource
    {
        return $this;
    }
}
