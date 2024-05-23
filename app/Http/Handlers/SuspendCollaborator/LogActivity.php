<?php

declare(strict_types=1);

namespace App\Http\Handlers\SuspendCollaborator;

use App\DataTransferObjects\Activities\CollaboratorSuspendedActivityLogData;
use App\Enums\ActivityType;
use App\Models\Folder;
use App\Models\FolderActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class LogActivity implements Scope
{
    public function __construct(
        private readonly User $authUser,
        private readonly ?int $suspensionPeriodInHours,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder, Model $model)
    {
        $expression = "JSON_OBJECT('id', id, 'full_name', full_name, 'public_id', public_id, 'profile_image_path', profile_image_path)";

        $builder->withCasts(['suspendedUser' => 'array']);

        $builder->addSelect([
            'suspendedUser' => User::selectRaw($expression)->whereColumn('id', 'collaboratorId')
        ]);
    }

    public function __invoke(Folder $folder): void
    {
        $activityData = new CollaboratorSuspendedActivityLogData(
            new User($folder->suspendedUser),
            $this->authUser,
            $this->suspensionPeriodInHours
        );

        $attributes = [
            'folder_id' => $folder->id,
            'type'      => ActivityType::USER_SUSPENDED,
            'data'      => $activityData->toArray(),
        ];

        dispatch(static function () use ($attributes) {
            FolderActivity::query()->create($attributes);
        })->afterResponse();
    }
}
