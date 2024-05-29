<?php

declare(strict_types=1);

namespace App\Http\Handlers\RevokeCollaboratorRole;

use App\Collections\RolesPublicIdsCollection;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderCollaboratorRole;
use App\Models\FolderRole;
use App\Models\Scopes\WherePublicIdScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CollaboratorMustHaveRolesConstraint implements Scope
{
    public function __construct(private readonly RolesPublicIdsCollection $roles)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->withCasts(['numberOfRolesAssignedToCollaborator' => 'integer'])
            ->addSelect([
                'numberOfRolesAssignedToCollaborator' => FolderCollaboratorRole::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('collaborator_id', 'collaboratorId')
                    ->whereIn('role_id', FolderRole::select('id')->tap(new WherePublicIdScope($this->roles->values())))
            ]);
    }

    public function __invoke(Folder $result): void
    {
        if ($result->numberOfRolesAssignedToCollaborator !== $this->roles->values()->count()) {
            throw HttpException::notFound([
                'message' => 'CollaboratorHasNoSuchRole',
                'info'    => ''
            ]);
        }
    }
}
