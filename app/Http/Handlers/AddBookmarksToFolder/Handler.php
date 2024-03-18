<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Models\Folder;
use App\Models\Bookmark;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;

final class Handler
{
    public function handle(int $folderId, Data $data): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data));

        $query = Folder::query()->select(['id']);

        $bookmarks = Bookmark::query()->findMany($data->bookmarkIds, ['user_id', 'id', 'url'])->all();

        $requestHandlersQueue->scope($query, function ($handler) use ($bookmarks) {
            if ($handler instanceof BookmarksAwareInterface) {
                $handler->setBookmarks($bookmarks);
            }
        });

        $folder = $query->findOr($folderId, callback: fn () => new Folder());

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(Data $data): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustBeACollaboratorConstraint(),
            new Constraints\PermissionConstraint($data->authUser, Permission::ADD_BOOKMARKS),
            new Constraints\FeatureMustBeEnabledConstraint($data->authUser, Feature::ADD_BOOKMARKS),
            new MaxFolderBookmarksConstraint($data),
            new UserOwnsBookmarksConstraint($data),
            new BookmarksExistsConstraint($data),
            new CollaboratorCannotMarkBookmarksAsHiddenConstraint($data),
            new UniqueFolderBookmarkConstraint($data),
            new CreateFolderBookmarks($data),
            new SendBookmarksAddedToFolderNotification($data),
            new CheckBookmarksHealth(),
        ];
    }
}
