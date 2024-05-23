<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\Collections\BookmarkPublicIdsCollection;
use App\Models\Folder;
use App\Models\Bookmark;
use App\Models\FolderBookmark;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;
use App\DataTransferObjects\RemoveFolderBookmarksRequestData as Data;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\CollaboratorMetricsRecorder;
use App\Http\Handlers\ConditionallyLogActivity;
use App\Http\Handlers\SuspendCollaborator\SuspendedCollaboratorFinder;
use App\Models\Scopes\WherePublicIdScope;
use App\ValueObjects\PublicId\FolderPublicId;

final class Handler
{
    public function handle(FolderPublicId $folderId, Data $data): void
    {
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromRequest($data->bookmarkIds)->values();

        $query = Folder::query()->select(['id'])->tap(new WherePublicIdScope($folderId));

        $whereIn = FolderBookmark::query()
            ->select('bookmark_id')
            ->where('folder_id', Folder::select('id')->tap(new WherePublicIdScope($folderId)));

        $folderBookmarks = Bookmark::select(['id', 'url', 'public_id'])
            ->tap(new WherePublicIdScope($bookmarksPublicIds))
            ->whereIn('id', $whereIn)
            ->get();

        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data, $folderBookmarks->all()));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(Data $data, array $folderBookmarks): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            $suspendedCollaboratorRepository = new SuspendedCollaboratorFinder($data->authUser),
            new Constraints\MustBeACollaboratorConstraint($data->authUser),
            new Constraints\PermissionConstraint($data->authUser, Permission::DELETE_BOOKMARKS),
            new Constraints\FeatureMustBeEnabledConstraint($data->authUser, Feature::DELETE_BOOKMARKS),
            new Constraints\MustNotBeSuspendedConstraint($suspendedCollaboratorRepository),
            new FolderContainsBookmarksConstraint($data, $folderBookmarks),
            new DeleteFolderBookmarks($folderBookmarks),
            new SendBookmarksRemovedFromFolderNotificationNotification($data, $folderBookmarks),
            new CollaboratorMetricsRecorder(CollaboratorMetricType::BOOKMARKS_DELETED, $data->authUser->id, count($folderBookmarks)),
            new ConditionallyLogActivity(new LogActivity($folderBookmarks, $data->authUser))
        ];
    }
}
