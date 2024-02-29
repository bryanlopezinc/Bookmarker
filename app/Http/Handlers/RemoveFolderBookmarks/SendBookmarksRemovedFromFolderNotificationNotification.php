<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\RemoveFolderBookmarksRequestData;
use App\Models\Folder;
use App\Models\Scopes\IsMutedCollaboratorScope;
use App\Models\User;
use App\Notifications\BookmarksRemovedFromFolderNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Notification;

final class SendBookmarksRemovedFromFolderNotificationNotification implements FolderRequestHandlerInterface, Scope
{
    public function __construct(private readonly RemoveFolderBookmarksRequestData $data)
    {
    }

    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['settings'])->tap(
            new IsMutedCollaboratorScope($this->data->authUser->id)
        );
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $folderSettings = $folder->settings;
        $folderBelongsToAuthUser = $this->data->authUser->id === $folder->user_id;

        if (
            $folderBelongsToAuthUser                                ||
            $folderSettings->notificationsAreDisabled                ||
            $folderSettings->bookmarksRemovedNotificationIsDisabled  ||
            $folder->collaboratorIsMuted
        ) {
            return;
        }

        Notification::send(
            new User(['id' => $folder->user_id]),
            new BookmarksRemovedFromFolderNotification($this->data->bookmarkIds, $folder, $this->data->authUser)
        );
    }
}
