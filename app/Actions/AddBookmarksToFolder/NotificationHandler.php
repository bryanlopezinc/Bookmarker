<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Models\Folder;
use App\Models\Scopes\IsMutedCollaboratorScope;
use App\Models\User;
use Illuminate\Support\Facades\Notification as NotificationSender;
use App\Notifications\BookmarksAddedToFolderNotification as Notification;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class NotificationHandler implements HandlerInterface, Scope
{
    private readonly User $authUser;

    public function __construct(User $authUser)
    {
        $this->authUser = $authUser;
    }

    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->addSelect(['user_id', 'settings'])->tap(new IsMutedCollaboratorScope($this->authUser->id));
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder, array $bookmarkIds): void
    {
        $settings = $folder->settings;

        if (
            $this->authUser->id === $folder->user_id ||
            $settings->notificationsAreDisabled()  ||
            $settings->newBookmarksNotificationIsDisabled() ||
            $folder->collaboratorIsMuted
        ) {
            return;
        }

        NotificationSender::send(
            new User(['id' => $folder->user_id]),
            new Notification($bookmarkIds, $folder, $this->authUser)
        );
    }
}
