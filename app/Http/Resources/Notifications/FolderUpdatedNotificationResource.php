<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Folder;
use App\Contracts\TransformsNotificationInterface;
use App\DataTransferObjects\DatabaseNotification;
use App\DataTransferObjects\User;
use App\Repositories\FetchNotificationResourcesRepository as Repository;
use Illuminate\Http\Resources\Json\JsonResource;

final class FolderUpdatedNotificationResource extends JsonResource implements TransformsNotificationInterface
{
    public function __construct(private DatabaseNotification $notification, private Repository $repository)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $updatedBy = $this->getCollaborator();
        $folder = $this->getFolder();

        return [
            'type' => 'FolderUpdatedNotification',
            'attributes' => [
                'id' => $this->notification->id->value,
                'collaborator_exists' => $updatedBy !== null,
                'folder_exists' => $folder !== null,
                'collaborator' => $this->when($updatedBy !== null, fn () => [
                    'id' => $updatedBy->id->value(), // @phpstan-ignore-line
                    'first_name' => $updatedBy->firstName->value, // @phpstan-ignore-line
                    'last_name' => $updatedBy->lastName->value // @phpstan-ignore-line
                ]),
                'folder' => $this->when($folder !== null, fn () => [
                    'name' => $folder->name->safe(), // @phpstan-ignore-line
                    'id' => $folder->folderID->value() // @phpstan-ignore-line
                ]),
                'changes' => $this->getChanges(),
            ]
        ];
    }

    /**
     * @return array<string,array<string>>
     */
    private function getChanges(): array
    {
        return $this->notification->notificationData['changes'];
    }

    /**
     * Get the user that updated the folder
     */
    private function getCollaborator(): ?User
    {
        return $this->repository->findUserByID($this->notification->notificationData['updated_by']);
    }

    private function getFolder(): ?Folder
    {
        return $this->repository->findFolderByID($this->notification->notificationData['folder_updated']);
    }

    public function toJsonResource(): JsonResource
    {
        return $this;
    }
}
