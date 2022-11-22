<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Folder;
use App\Contracts\TransformsNotificationInterface;
use App\DataTransferObjects\DatabaseNotification;
use App\DataTransferObjects\User;
use App\Repositories\FetchNotificationResourcesRepository as Repository;
use Illuminate\Http\Resources\Json\JsonResource;

final class BookmarksAddedToFolderNotificationResource extends JsonResource implements TransformsNotificationInterface
{
    public function __construct(private DatabaseNotification $notification, private Repository $repository)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $collaborator = $this->getCollaborator();
        $folder = $this->getFolder();
        $bookmarks = $this->getBookmarks();

        return [
            'type' => 'BookmarksAddedToFolderNotification',
            'attributes' => [
                'id' => $this->notification->id->value,
                'collaborator_exists' => $collaborator !== null,
                'folder_exists' => $folder !== null,
                'bookmarks_count' => count($bookmarks),
                'by_collaborator' => $this->when($collaborator !== null, fn () => [
                    'id' => $collaborator->id->value(), // @phpstan-ignore-line
                    'first_name' => $collaborator->firstName->value, // @phpstan-ignore-line
                    'last_name' => $collaborator->lastName->value // @phpstan-ignore-line
                ]),
                'folder' => $this->when($folder !== null, fn () => [
                    'name' => $folder->name->safe(), // @phpstan-ignore-line
                    'id' => $folder->folderID->value() // @phpstan-ignore-line
                ]),
                'bookmarks' => array_map(fn (Bookmark $bookmark) => [
                    'title' => $bookmark->title->safe()
                ], $bookmarks)
            ]
        ];
    }

    /**
     * Get the user that added the bookmarks
     */
    private function getCollaborator(): ?User
    {
        return $this->repository->findUserByID($this->notification->notificationData['added_by']);
    }

    private function getFolder(): ?Folder
    {
        return $this->repository->findFolderByID($this->notification->notificationData['added_to_folder']);
    }

    /**
     * @return array<Bookmark>
     */
    private function getBookmarks(): array
    {
        return $this->repository->findBookmarksByIDs($this->notification->notificationData['bookmarks_added_to_folder']);
    }

    public function toJsonResource(): JsonResource
    {
        return $this;
    }
}
