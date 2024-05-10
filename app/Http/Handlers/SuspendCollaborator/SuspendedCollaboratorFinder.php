<?php

declare(strict_types=1);

namespace App\Http\Handlers\SuspendCollaborator;

use App\Models\Folder;
use App\Models\SuspendedCollaborator as Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

final class SuspendedCollaboratorFinder implements Scope
{
    private readonly User $authUser;
    private array $record;

    public function __construct(User $authUser = new User())
    {
        $this->authUser = $authUser;
    }

    public function apply(Builder $builder, EloquentModel $model)
    {
        $builder->withCasts(['suspensionData' => 'array']);

        $builder->addSelect([
            'suspensionData' => Model::query()
                ->select(DB::raw("JSON_OBJECT('id', id, 'suspended_at', suspended_at, 'duration_in_hours', duration_in_hours)"))
                ->whereColumn('folder_id', 'folders.id')
                ->when(
                    value: $this->authUser->exists,
                    callback: fn ($query) => $query->where('collaborator_id', $this->authUser->id),
                    default: fn ($query) => $query->whereColumn('collaborator_id', 'collaboratorId'),
                )
        ]);
    }

    public function __invoke(Folder $result): void
    {
        $this->record = $result->suspensionData ?? [];
    }

    public function getRecord(): Model
    {
        return tap(new Model($this->record), function (Model $model) {
            $model->exists = true;
        });
    }

    public function collaboratorIsSuspended(): bool
    {
        return $this->record !== [];
    }
}
