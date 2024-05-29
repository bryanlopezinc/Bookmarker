<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\Enums\ActivityType;
use App\Models\Folder;
use App\Models\FolderActivity;
use Illuminate\Contracts\Foundation\Application;
use App\DataTransferObjects\Activities\InviteAcceptedActivityLogData as ActivityLogData;

final class LogActivity
{
    private readonly UserRepository $repository;
    private readonly Application $app;

    public function __construct(UserRepository $repository, Application $app = null)
    {
        $this->repository = $repository;
        $this->app = $app ??= app();
    }

    public function __invoke(Folder $folder): void
    {
        $activityData = new ActivityLogData(
            $this->repository->inviter(),
            $this->repository->invitee()
        );

        $attributes = [
            'folder_id' => $folder->id,
            'type'      => ActivityType::NEW_COLLABORATOR,
            'data'      => $activityData->toArray(),
        ];

        $pendingDispatch = dispatch(static function () use ($attributes) {
            FolderActivity::query()->create($attributes);
        });

        if ( ! $this->app->runningUnitTests()) {
            $pendingDispatch->afterResponse();
        }
    }
}
