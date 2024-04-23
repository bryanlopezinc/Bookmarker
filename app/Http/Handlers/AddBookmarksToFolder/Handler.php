<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Collections\BookmarkPublicIdsCollection as PublicIds;
use App\Models\Folder;
use App\Models\Bookmark;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\CollaboratorMetricsRecorder;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\Scopes\WherePublicIdScope;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Support\Collection;

final class Handler
{
    public function handle(FolderPublicId $folderId, Data $data): void
    {
        $query = Folder::select(['id'])->tap(new WherePublicIdScope($folderId));

        $bookmarks = Bookmark::query()
            ->tap(new WherePublicIdScope(PublicIds::fromRequest($data->bookmarksPublicIds)->values()))
            ->get(['user_id', 'id', 'url', 'public_id']);

        $requestHandlersQueue = new RequestHandlersQueue(
            $this->getConfiguredHandlers($data, $bookmarks)
        );

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(Data $data, Collection $bookmarks): array
    {
        $bookmarksIds = $bookmarks->pluck('id')->all();

        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustBeACollaboratorConstraint($data->authUser),
            new Constraints\PermissionConstraint($data->authUser, Permission::ADD_BOOKMARKS),
            new Constraints\FeatureMustBeEnabledConstraint($data->authUser, Feature::ADD_BOOKMARKS),
            new MaxFolderBookmarksConstraint($data),
            new UserOwnsBookmarksConstraint($data, $bookmarks->all()),
            new BookmarksExistsConstraint($data, $bookmarks->all()),
            new CollaboratorCannotMarkBookmarksAsHiddenConstraint($data),
            new UniqueFolderBookmarkConstraint($bookmarksIds),
            new CreateFolderBookmarks($bookmarks->all(), $data),
            new SendBookmarksAddedToFolderNotification($data, $bookmarksIds),
            new CheckBookmarksHealth($bookmarks->all()),
            new CollaboratorMetricsRecorder(CollaboratorMetricType::BOOKMARKS_ADDED, $data->authUser->id, $bookmarks->count())
        ];
    }
}
