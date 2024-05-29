<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Models\Folder;
use App\Models\User;
use App\Notifications\YouHaveBeenBootedOutNotification;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Notification;

final class NotifyCollaborator implements Scope
{
    private readonly Application $application;

    public function __construct(Application $application = null)
    {
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
        $collaboratorId = $folder->collaboratorId;

        $pendingDispatch = dispatch(static function () use ($folder, $collaboratorId) {
            $notification = new YouHaveBeenBootedOutNotification($folder);

            Notification::send(
                new User(['id' => $collaboratorId]),
                $notification
            );
        });

        if ( ! $this->application->runningUnitTests()) {
            $pendingDispatch->afterResponse();
        }
    }
}
