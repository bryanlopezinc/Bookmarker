<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Models\Folder;
use App\Models\Bookmark;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\CollaboratorMetricsRecorder;
use App\Http\Handlers\RequestHandlersQueue;

final class Handler
{
    public function handle(int $folderId, Data $data): void
    {
        $query = Folder::query()->select(['id'])->whereKey($folderId);

        $bookmarks = Bookmark::query()->findMany($data->bookmarkIds, ['user_id', 'id', 'url'])->all();

        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data, $bookmarks, $folderId));

        $requestHandlersQueue->scope($query);

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(Data $data, array $bookmarks, int $folderId): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustBeACollaboratorConstraint($data->authUser),
            new Constraints\PermissionConstraint($data->authUser, Permission::ADD_BOOKMARKS),
            new Constraints\FeatureMustBeEnabledConstraint($data->authUser, Feature::ADD_BOOKMARKS),
            new MaxFolderBookmarksConstraint($data),
            new UserOwnsBookmarksConstraint($data, $bookmarks),
            new BookmarksExistsConstraint($data, $bookmarks),
            new CollaboratorCannotMarkBookmarksAsHiddenConstraint($data),
            new UniqueFolderBookmarkConstraint($data, $folderId),
            new CreateFolderBookmarks($data),
            new SendBookmarksAddedToFolderNotification($data),
            new CheckBookmarksHealth($bookmarks),
            new CollaboratorMetricsRecorder(CollaboratorMetricType::BOOKMARKS_ADDED, $data->authUser->id, count($bookmarks))
        ];
    }
}
