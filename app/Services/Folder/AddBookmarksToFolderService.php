<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Enums\FolderBookmarkVisibility as Visibility;
use App\Enums\Permission;
use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\FolderActionDisabledException;
use App\Exceptions\FolderNotFoundException;
use App\Repositories\BookmarkRepository;
use App\Exceptions\HttpException as HttpException;
use App\Exceptions\PermissionDeniedException;
use App\Http\Requests\AddBookmarksToFolderRequest as Request;
use App\Jobs\CheckBookmarksHealth;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\Scopes\DisabledActionScope;
use App\Models\Scopes\IsMutedCollaboratorScope;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Models\User;
use App\Notifications\BookmarksAddedToFolderNotification as Notification;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\ValueObjects\FolderStorage;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationSender;

final class AddBookmarksToFolderService
{
    public function __construct(
        private BookmarkRepository $bookmarksRepository,
        private CollaboratorPermissionsRepository $permissions,
    ) {
    }

    public function fromRequest(Request $request): void
    {
        /** @var User */
        $authUser = auth()->user();

        $bookmarkIds = $request->getBookmarkIds();

        $folder = Folder::onlyAttributes(['id', 'user_id', 'settings', 'bookmarks_count', 'name'])
            ->tap(new WhereFolderOwnerExists())
            ->tap(new DisabledActionScope(Permission::ADD_BOOKMARKS))
            ->tap(new IsMutedCollaboratorScope($authUser->id))
            ->find($request->integer('folder'));

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        $bookmarks = $this->bookmarksRepository->findManyById($bookmarkIds, ['user_id', 'id', 'url']);

        $this->ensureUserHasPermissionToPerformAction($folder, $authUser->id);
        $this->ensureFolderCanContainBookmarks($bookmarkIds, $folder);
        $this->ensureBookmarksExistAndBelongToUser($bookmarks, $bookmarkIds);
        $this->ensureCollaboratorCannotMarkBookmarksAsHidden($request, $folder, $authUser->id);
        $this->ensureFolderDoesNotContainBookmarks($folder->id, $bookmarkIds);

        $this->add($folder->id, $bookmarkIds, $request->input('make_hidden', []));

        $folder->touch();

        dispatch(new CheckBookmarksHealth($bookmarks));

        $this->notifyFolderOwner($bookmarkIds, $folder, $authUser);
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
                'visibility'  => $makeHidden->contains($bookmarkID) ?
                    Visibility::PRIVATE->value :
                    Visibility::PUBLIC->value
            ])
            ->tap(fn (Collection $data) => FolderBookmark::insert($data->all()));
    }

    private function ensureCollaboratorCannotMarkBookmarksAsHidden(
        Request $request,
        Folder $folder,
        int $authUserId
    ): void {
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
        $folderBelongsToAuthUser = $folder->user_id === auth()->id();

        try {
            FolderNotFoundException::throwIf(!$folderBelongsToAuthUser);
        } catch (FolderNotFoundException $e) {
            $userFolderAccess = $this->permissions->all($authUserId, $folder->id);

            if ($userFolderAccess->isEmpty()) {
                throw $e;
            }

            if ($folder->actionIsDisable) {
                throw new FolderActionDisabledException(Permission::ADD_BOOKMARKS);
            }

            if (!$userFolderAccess->canAddBookmarks()) {
                throw new PermissionDeniedException(Permission::ADD_BOOKMARKS);
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
            throw new BookmarkNotFoundException();
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

    private function notifyFolderOwner(array $bookmarkIDs, Folder $folder, User $authUser): void
    {
        $settings = $folder->settings;

        if (
            $authUser->id === $folder->user_id ||
            $settings->notificationsAreDisabled()  ||
            $settings->newBookmarksNotificationIsDisabled() ||
            $folder->collaboratorIsMuted
        ) {
            return;
        }

        NotificationSender::send(
            new User(['id' => $folder->user_id]),
            new Notification($bookmarkIDs, $folder, $authUser)
        );
    }
}
