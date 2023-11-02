<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\FolderSettings;
use App\Enums\FolderBookmarkVisibility as Visibility;
use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\FolderNotFoundException;
use App\Repositories\BookmarkRepository;
use App\ValueObjects\UserId;
use App\Exceptions\HttpException as HttpException;
use App\Http\Requests\AddBookmarksToFolderRequest as Request;
use App\Jobs\CheckBookmarksHealth;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\User;
use App\Notifications\BookmarksAddedToFolderNotification as Notification;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\ValueObjects\FolderStorage;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;

final class AddBookmarksToFolderService
{
    public function __construct(
        private FetchFolderService $repository,
        private BookmarkRepository $bookmarksRepository,
        private FolderPermissionsRepository $permissions,
    ) {
    }

    public function fromRequest(Request $request): void
    {
        $authUserId = UserId::fromAuthUser()->value();
        $folderId = $request->integer('folder');
        $bookmarkIds = $request->collect('bookmarks')->map(fn (string $id) => (int) $id)->all();

        $folder = $this->repository->find($folderId, ['id', 'user_id', 'bookmarks_count', 'settings']);

        $bookmarks = $this->bookmarksRepository->findManyById($bookmarkIds, ['user_id', 'id', 'url']);

        $this->ensureUserHasPermissionToPerformAction($folder, $authUserId);
        $this->ensureFolderCanContainBookmarks($bookmarkIds, $folder);
        $this->ensureBookmarksExistAndBelongToUser($bookmarks, $bookmarkIds);
        $this->ensureCollaboratorCannotMarkBookmarksAsHidden($request, $folder, $authUserId);
        $this->ensureFolderDoesNotContainBookmarks($folderId, $bookmarkIds);

        $this->add($folderId, $bookmarkIds, $request->input('make_hidden', []));

        $folder->touch();

        dispatch(new CheckBookmarksHealth($bookmarks));

        $this->notifyFolderOwner($bookmarkIds, $folder);
    }

    /**
     * @param int|array<int> $bookmarkIds
     * @param array<int> $hidden
     */
    public function add(int $folderId, array|int $bookmarkIds, array $hidden = []): void
    {
        $makeHidden = collect($hidden);

        collect((array)$bookmarkIds)
            ->map(fn (int $bookmarkID) => [
                'bookmark_id' => $bookmarkID,
                'folder_id'   => $folderId,
                'visibility'  => $makeHidden->contains($bookmarkID) ? Visibility::PRIVATE->value : Visibility::PUBLIC->value
            ])
            ->tap(fn (Collection $data) => FolderBookmark::insert($data->all()));
    }

    private function ensureCollaboratorCannotMarkBookmarksAsHidden(Request $request, Folder $folder, int $authUserId): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $authUserId;

        if ($request->missing('make_hidden') || $folderBelongsToAuthUser) {
            return;
        }

        throw new HttpResponseException(
            response()->json(['message' => 'collaboratorCannotMakeBookmarksHidden'], 400)
        );
    }

    private function ensureUserHasPermissionToPerformAction(Folder $folder, int $authUserId): void
    {
        try {
            FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);
        } catch (FolderNotFoundException $e) {
            $userFolderAccess = $this->permissions->getUserAccessControls($authUserId, $folder->id);

            if ($userFolderAccess->isEmpty()) {
                throw $e;
            }

            if (!$userFolderAccess->canAddBookmarks()) {
                throw new HttpResponseException(
                    response()->json(['message' => 'NoAddBookmarkPermission'], 403)
                );
            }
        }
    }

    private function ensureFolderCanContainBookmarks(array $bookmarkIds, Folder $folder): void
    {
        $storage = new FolderStorage($folder->bookmarksCount);

        if (!$storage->canContain($bookmarkIds)) {
            throw HttpException::forbidden(['message' => 'folderBookmarksLimitReached']);
        }
    }

    /**
     * @param Collection<Bookmark> $bookmarks
     */
    private function ensureBookmarksExistAndBelongToUser(Collection $bookmarks, array $bookmarksToAddToFolder): void
    {
        if ($bookmarks->count() !== count($bookmarksToAddToFolder)) {
            throw new BookmarkNotFoundException;
        }

        $bookmarks->each(function (Bookmark $bookmark) {
            BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);
        });
    }

    private function ensureFolderDoesNotContainBookmarks(int $folderID, array $bookmarkIDs): void
    {
        $hasBookmarks = FolderBookmark::where('folder_id', $folderID)
            ->whereIntegerInRaw('bookmark_id', $bookmarkIDs)
            ->count() > 0;

        if ($hasBookmarks) {
            throw HttpException::conflict(['message' => 'FolderContainsBookmarks']);
        }
    }

    private function notifyFolderOwner(array $bookmarkIDs, Folder $folder): void
    {
        $collaboratorID = UserId::fromAuthUser()->value();

        $settings = FolderSettings::fromQuery($folder->settings);

        if (
            $collaboratorID === $folder->user_id ||
            $settings->notificationsAreDisabled()  ||
            $settings->newBookmarksNotificationIsDisabled()
        ) {
            return;
        }

        (new User(['id' => $folder->user_id]))->notify(
            new Notification($bookmarkIDs, $folder->id, $collaboratorID)
        );
    }
}
