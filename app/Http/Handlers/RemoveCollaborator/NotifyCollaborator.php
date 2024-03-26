<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Contracts\FolderRequestHandlerInterface;
use App\Models\Folder;
use App\DataTransferObjects\RemoveCollaboratorData as Data;
use App\Models\User;
use App\Notifications\YouHaveBeenBootedOutNotification;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Notification;

final class NotifyCollaborator implements FolderRequestHandlerInterface, Scope
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

        $pendingDispatch = dispatch(static function () use ($folder, $dataArray) {
            $notification = new YouHaveBeenBootedOutNotification($folder);

            Notification::send(
                new User(['id' => $dataArray['collaboratorId']]),
                $notification
            );
        });

        if ( ! $this->application->runningUnitTests()) {
            $pendingDispatch->afterResponse();
        }
    }
}
