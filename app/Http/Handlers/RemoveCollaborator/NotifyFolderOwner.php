<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Models\Folder;
use App\DataTransferObjects\RemoveCollaboratorData as Data;
use App\Models\User;
use App\Notifications\CollaboratorRemovedNotification;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Notification;

final class NotifyFolderOwner implements Scope
{
    private readonly Application $application;
    private readonly Data $data;

    public function __construct(Data $data, Application $application = null)
    {
        $this->data = $data;
        $this->application = $application ??= app();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder
            ->withCasts(['collaboratorRemoved' => 'json'])
            ->addSelect([
                'name',
                'collaboratorRemoved' => User::query()
                    ->select(new Expression("JSON_OBJECT('id', id, 'full_name', full_name, 'public_id', public_id)"))
                    ->whereColumn('id', 'collaboratorId')
            ]);
    }

    public function __invoke(Folder $folder): void
    {
        $notificationData = $this->prepareNotificationDataForClosureSerialization($folder);

        if ($folder->wasCreatedBy($this->data->authUser)) {
            return;
        }

        $pendingDispatch = dispatch(static function () use ($notificationData, $folder) {
            $notification = new CollaboratorRemovedNotification(
                $folder,
                new User($notificationData['collaboratorRemoved']),
                new User($notificationData['removedBy']),
                $notificationData['ban']
            );

            Notification::send(
                new User(['id' => $folder->user_id]),
                $notification
            );
        });

        if ( ! $this->application->runningUnitTests()) {
            $pendingDispatch->afterResponse();
        }
    }

    private function prepareNotificationDataForClosureSerialization(Folder $folder): array
    {
        return [
            'ban'                 => $this->data->ban,
            'removedBy'           => $this->data->authUser->getAttributes(),
            'collaboratorRemoved' => $folder->collaboratorRemoved
        ];
    }
}
