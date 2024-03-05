<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Models\Folder;
use App\Models\User;
use App\Notifications\FolderUpdatedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Notification;

final class SendFolderUpdatedNotification implements FolderRequestHandlerInterface, Scope
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

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $wasUpdatedByFolderOwner = $folder->user_id === $this->data->authUser->id;
        $settings = $folder->settings;
        $notifications = [];

        if (
            $wasUpdatedByFolderOwner ||
            $settings->notificationsAreDisabled ||
            $settings->folderUpdatedNotificationIsDisabled
        ) {
            return;
        }

        foreach (array_keys($folder->getDirty()) as $modified) {
            if (!in_array($modified, ['name', 'description'])) {
                continue;
            }

            $notifications[] = new FolderUpdatedNotification($folder, $this->data->authUser, $modified);
        }

        foreach ($notifications as $notification) {
            Notification::send(
                new User(['id' => $folder->user_id]),
                $notification
            );
        }
    }
}