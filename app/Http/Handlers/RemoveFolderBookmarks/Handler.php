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
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\CollaboratorMetricsRecorder;

final class Handler
{
    public function handle(int $folderId, Data $data): void
    {
        $query = Folder::query()->select(['id'])->whereKey($folderId);

        $folderBookmarks = FolderBookmark::query()
            ->where('folder_id', $folderId)
            ->whereIntegerInRaw('bookmark_id', $data->bookmarkIds)
            ->whereExists(Bookmark::whereRaw('id = folders_bookmarks.bookmark_id'))
            ->get()
            ->all();

        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data, $folderBookmarks));

        $requestHandlersQueue->scope($query);

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(Data $data, array $folderBookmarks): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustBeACollaboratorConstraint($data->authUser),
            new Constraints\PermissionConstraint($data->authUser, Permission::DELETE_BOOKMARKS),
            new Constraints\FeatureMustBeEnabledConstraint($data->authUser, Feature::DELETE_BOOKMARKS),
            new FolderContainsBookmarksConstraint($data, $folderBookmarks),
            new DeleteFolderBookmarks($folderBookmarks),
            new SendBookmarksRemovedFromFolderNotificationNotification($data),
            new CollaboratorMetricsRecorder(CollaboratorMetricType::BOOKMARKS_DELETED, $data->authUser->id, count($folderBookmarks))
        ];
    }
}
