<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Models\Folder;
use App\Models\User;
use App\Notifications\FolderDescriptionChangedNotification;
use App\Notifications\FolderIconUpdatedNotification;
use App\Notifications\FolderNameChangedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Notification;

final class SendFolderUpdatedNotification implements Scope
{
    private array $changes;

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

    public function setChanges(array $changes): void
    {
        $this->changes = $changes;
    }

    public function __invoke(Folder $folder): void
    {
        $wasUpdatedByFolderOwner = $folder->user_id === $this->data->authUser->id;

        $settings = $folder->settings;
        $changes = $this->changes;
        $authUser = $this->data->authUser;

        if (
            $wasUpdatedByFolderOwner                ||
            $settings->notifications()->isDisabled() ||
            $settings->folderUpdatedNotification()->isDisabled()
        ) {
            return;
        }

        dispatch(static function () use ($folder, $changes, $authUser) {
            foreach (array_keys($changes) as $modified) {
                $notification = match ($modified) {
                    'name'        => new FolderNameChangedNotification($folder, $authUser),
                    'description' => new FolderDescriptionChangedNotification($folder, $authUser),
                    default       => new FolderIconUpdatedNotification($folder, $authUser),
                };

                Notification::send(new User(['id' => $folder->user_id]), $notification);
            }
        })->afterResponse();
    }
}
