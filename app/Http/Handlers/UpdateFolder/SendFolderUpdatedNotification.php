<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Models\Folder;
use App\Models\User;
use App\Notifications\FolderDescriptionUpdatedNotification;
use App\Notifications\FolderIconUpdatedNotification;
use App\Notifications\FolderNameUpdatedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Notification;

final class SendFolderUpdatedNotification implements Scope
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect(['settings', 'user_id']);
    }

    public function __invoke(Folder $folder): void
    {
        $wasUpdatedByFolderOwner = $folder->user_id === $this->data->authUser->id;
        $settings = $folder->settings;

        if (
            $wasUpdatedByFolderOwner ||
            $settings->notificationsAreDisabled ||
            $settings->folderUpdatedNotificationIsDisabled
        ) {
            return;
        }

        foreach (array_keys($folder->getDirty()) as $modified) {
            $notification = match ($modified) {
                'name'        => new FolderNameUpdatedNotification($folder, $this->data->authUser),
                'description' => new FolderDescriptionUpdatedNotification($folder, $this->data->authUser),
                default       => new FolderIconUpdatedNotification($folder, $this->data->authUser),
            };

            $pendingDispatch = dispatch(static function () use ($folder, $notification) {
                Notification::send(new User(['id' => $folder->user_id]), $notification);
            });

            $pendingDispatch->afterResponse();
        }
    }
}
