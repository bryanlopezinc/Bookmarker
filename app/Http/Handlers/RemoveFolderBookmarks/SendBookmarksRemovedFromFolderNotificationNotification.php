<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\DataTransferObjects\RemoveFolderBookmarksRequestData as Data;
use App\Models\Folder;
use App\Models\Scopes\IsMutedCollaboratorScope;
use App\Models\User;
use App\Notifications\BookmarksRemovedFromFolderNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use App\Models\FolderBookmark;

final class SendBookmarksRemovedFromFolderNotificationNotification implements Scope
{
    /**
     * @param array<FolderBookmark> $folderBookmarks
     */
    public function __construct(private readonly Data $data, private readonly array $folderBookmarks)
    {
    }

    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['settings'])->tap(
            new IsMutedCollaboratorScope($this->data->authUser->id)
        );
    }

    public function __invoke(Folder $folder): void
    {
        $folderSettings = $folder->settings;

        $folderBelongsToAuthUser = $this->data->authUser->id === $folder->user_id;

        [$authUser, $bookmarkIds] = [
            $this->data->authUser,
            Arr::pluck($this->folderBookmarks, 'bookmark_id')
        ];

        if (
            $folderBelongsToAuthUser                                ||
            $folderSettings->notificationsAreDisabled                ||
            $folderSettings->bookmarksRemovedNotificationIsDisabled  ||
            $folder->collaboratorIsMuted
        ) {
            return;
        }

        $pendingDispatch = dispatch(static function () use ($folder, $authUser, $bookmarkIds) {
            Notification::send(
                new User(['id' => $folder->user_id]),
                new BookmarksRemovedFromFolderNotification($bookmarkIds, $folder, $authUser)
            );
        });

        $pendingDispatch->afterResponse();
    }
}
