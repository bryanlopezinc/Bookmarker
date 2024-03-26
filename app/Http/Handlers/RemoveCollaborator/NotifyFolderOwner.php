<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Contracts\FolderRequestHandlerInterface;
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

final class NotifyFolderOwner implements FolderRequestHandlerInterface, Scope
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
                    ->select(new Expression("JSON_OBJECT('id', id, 'full_name', full_name)"))
                    ->where('id', $this->data->collaboratorId)
            ]);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $dataArray = $this->data->toArray();

        $collaboratorRemoved = $folder->collaboratorRemoved;

        $collaboratorWasRemovedByFolderOwner = $dataArray['authUser']->id === $folder->user_id;

        if ($collaboratorWasRemovedByFolderOwner) {
            return;
        }

        $pendingDispatch = dispatch(static function () use ($dataArray, $folder, $collaboratorRemoved) {
            $notification = new CollaboratorRemovedNotification(
                $folder,
                new User($collaboratorRemoved),
                $dataArray['authUser'],
                $dataArray['ban']
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
}
