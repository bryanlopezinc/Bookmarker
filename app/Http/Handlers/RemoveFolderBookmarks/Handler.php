<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\Models\Folder;
use App\Models\Bookmark;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Models\FolderBookmark;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;
use App\DataTransferObjects\RemoveFolderBookmarksRequestData as Data;
use App\Enums\Feature;
use App\Enums\Permission;

final class Handler
{
    public function handle(int $folderId, Data $data): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data));

        $query = Folder::query()->select(['id'])->whereKey($folderId);

        $folderBookmarks = FolderBookmark::query()
            ->where('folder_id', $folderId)
            ->whereIntegerInRaw('bookmark_id', $data->bookmarkIds)
            ->whereExists(Bookmark::whereRaw('id = folders_bookmarks.bookmark_id'))
            ->get()
            ->all();

        $requestHandlersQueue->scope($query, function ($handler) use ($folderBookmarks) {
            if ($handler instanceof FolderBookmarksAwareInterface) {
                $handler->setBookmarks($folderBookmarks);
            }
        });

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(Data $data): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustBeACollaboratorConstraint(),
            new Constraints\PermissionConstraint($data->authUser, Permission::DELETE_BOOKMARKS),
            new Constraints\FeatureMustBeEnabledConstraint($data->authUser, Feature::DELETE_BOOKMARKS),
            new FolderContainsBookmarksConstraint($data),
            new DeleteFolderBookmarks(),
            new SendBookmarksRemovedFromFolderNotificationNotification($data),
        ];
    }
}
