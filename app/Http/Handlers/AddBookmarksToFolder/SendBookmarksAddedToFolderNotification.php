<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

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
use Illuminate\Support\Collection;

final class SendBookmarksAddedToFolderNotification implements Scope
{
    public function __construct(private readonly Data $data, private readonly Collection $bookmarks)
    {
    }

    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->addSelect(['user_id', 'settings'])->tap(new IsMutedCollaboratorScope($this->data->authUser->id));
    }

    public function __invoke(Folder $folder): void
    {
        $settings = $folder->settings;
        $authUser = $this->data->authUser;
        $bookmarks = $this->bookmarks;

        $shouldNotSendNotification =
            $folder->wasCreatedBy($authUser)                   ||
            $settings->notifications()->isDisabled()            ||
            $settings->newBookmarksNotification()->isDisabled() ||
            $folder->collaboratorIsMuted;

        if ($shouldNotSendNotification) {
            return;
        }

        $pendingDispatch = dispatch(static function () use ($folder, $authUser, $bookmarks) {
            NotificationSender::send(
                new User(['id' => $folder->user_id]),
                new Notification(
                    $bookmarks->all(),
                    $folder,
                    $authUser
                )
            );
        });

        $pendingDispatch->afterResponse();
    }
}
