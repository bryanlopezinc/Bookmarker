<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\FolderBookmark;
use App\Enums\FolderBookmarkVisibility;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Jobs\CheckBookmarksHealth;
use App\Models\Bookmark;
use App\Models\Favorite;
use App\Models\Folder;
use App\Models\MutedCollaborator;
use App\Models\Scopes\UserIsCollaboratorScope;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\PaginationData;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

final class FetchFolderBookmarksService
{
    /**
     * @return Paginator<FolderBookmark>
     */
    public function __invoke(Request $request): Paginator
    {
        $authUserId = auth()->id();

        $folder = Folder::query()
            ->tap(new WhereFolderOwnerExists())
            ->when($authUserId, fn ($query) => $query->tap(new UserIsCollaboratorScope($authUserId)))
            ->find($request->route('folder_id'), ['id', 'user_id', 'visibility', 'password']);

        FolderNotFoundException::throwIf(!$folder);

        $fetchOnlyPublicBookmarks = (!$authUserId || $folder->user_id !== $authUserId);

        $this->ensureUserCanViewFolderBookmarks($folder, $request->input('folder_password'));

        $folderBookmarks = $this->getBookmarks(
            (int) $request->route('folder_id'),
            $fetchOnlyPublicBookmarks,
            $authUserId,
            PaginationData::fromRequest($request)
        );

        $folderBookmarks
            ->getCollection()
            ->map(fn (FolderBookmark $folderBookmark) => $folderBookmark->bookmark)
            ->tap(fn (Collection $bookmarks) => dispatch(new CheckBookmarksHealth($bookmarks)));

        return $folderBookmarks;
    }

    private function ensureUserCanViewFolderBookmarks(Folder $folder, ?string $folderPassword): void
    {
        if ($folder->visibility->isPublic()) {
            return;
        }

        try {
            FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);
        } catch (FolderNotFoundException $e) {
            if ($folder->visibility->isPasswordProtected()) {
                if (!$folderPassword) {
                    throw new HttpException(['message' => 'PasswordRequired'], Response::HTTP_BAD_REQUEST);
                }

                if (!Hash::check($folderPassword, $folder->password)) {
                    throw new AuthenticationException('InvalidFolderPassword');
                }

                return;
            }

            if (!$folder->userIsCollaborator) {
                throw $e;
            }
        }
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    private function getBookmarks(
        int $folderId,
        bool $onlyPublic,
        ?int $authUserId,
        PaginationData $pagination
    ): Paginator {

        /** @var Paginator */
        $result = Bookmark::WithQueryOptions()
            ->join('folders_bookmarks', 'folders_bookmarks.bookmark_id', '=', 'bookmarks.id')
            ->when($onlyPublic, fn ($query) => $query->where('visibility', FolderBookmarkVisibility::PUBLIC->value))
            ->when(!$onlyPublic, fn ($query) => $query->addSelect(['visibility']))
            ->when($authUserId, function ($query) use ($authUserId) {
                $query->addSelect([
                    'isUserFavorite' => Favorite::query()
                        ->select('id')
                        ->where('user_id', $authUserId)
                        ->whereColumn('bookmark_id', 'bookmarks.id')
                ]);
            })
            ->when($authUserId, function ($query) use ($authUserId, $folderId) {
                $mutedCollaboratorQuery = MutedCollaborator::query()
                    ->whereColumn('user_id', 'bookmarks.user_id')
                    ->where('folder_id', $folderId)
                    ->where('muted_by', $authUserId);

                $query->whereNotExists($mutedCollaboratorQuery);
            })
            ->where('folder_id', $folderId)
            ->latest('folders_bookmarks.id')
            ->simplePaginate($pagination->perPage(), [], page: $pagination->page());

        $result->setCollection(
            $result->getCollection()->map($this->buildFolderBookmarkObject($onlyPublic))
        );

        return $result;
    }

    private function buildFolderBookmarkObject(bool $onlyPublic): \Closure
    {
        return function (Bookmark $model) use ($onlyPublic) {
            $model->isUserFavorite = is_int($model->isUserFavorite);

            if ($onlyPublic) {
                $model->visibility = FolderBookmarkVisibility::PUBLIC->value;
            }

            return new FolderBookmark($model, FolderBookmarkVisibility::from($model->visibility));
        };
    }
}
