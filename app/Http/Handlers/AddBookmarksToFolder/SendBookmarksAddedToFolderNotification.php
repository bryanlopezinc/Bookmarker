<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\Models\Folder;
use App\Models\Scopes\IsMutedCollaboratorScope;
use App\Models\User;
use Illuminate\Support\Facades\Notification as NotificationSender;
use App\Notifications\BookmarksAddedToFolderNotification as Notification;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;

final class SendBookmarksAddedToFolderNotification implements FolderRequestHandlerInterface, Scope
{
    public function __construct(private readonly Data $data)
    {
    }

    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->addSelect(['user_id', 'settings'])->tap(new IsMutedCollaboratorScope($this->data->authUser->id));
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $settings = $folder->settings;
        $folderBelongsToAuthUser = $this->data->authUser->id === $folder->user_id;
        [$authUser, $bookmarkIds] = [$this->data->authUser, $this->data->bookmarkIds];

        if (
            $folderBelongsToAuthUser ||
            $settings->notificationsAreDisabled  ||
            $settings->newBookmarksNotificationIsDisabled ||
            $folder->collaboratorIsMuted
        ) {
            return;
        }

        $pendingDispatch = dispatch(static function () use ($folder, $authUser, $bookmarkIds) {
            NotificationSender::send(
                new User(['id' => $folder->user_id]),
                new Notification($bookmarkIds, $folder, $authUser)
            );
        });

        $pendingDispatch->afterResponse();
    }
}
