<?php

declare(strict_types=1);

namespace App\Http\Handlers\AssignRole;

use App\Models\Folder;
use App\Exceptions\HttpException;
use App\Models\FolderCollaboratorRole;
use App\Models\FolderRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UniqueConstraint implements Scope
{
    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model)
    {
        $whereExists = FolderCollaboratorRole::query()
            ->whereColumn('collaborator_id', 'collaboratorId')
            ->whereColumn('role_id', 'folders_roles.id');

        $builder
            ->withCasts(['collaboratorRoles' => 'json'])
            ->addSelect([
                'collaboratorRoles' => FolderRole::query()
                    ->selectRaw('JSON_ARRAYAGG(name)')
                    ->whereColumn('folder_id', $model->qualifyColumn('id'))
                    ->whereExists($whereExists)
            ]);
    }

    public function __invoke(Folder $folder): void
    {
        if ($roleNames = $folder->collaboratorRoles) {
            $roleNames = implode(',', $roleNames);

            throw HttpException::conflict([
                'message' => 'DuplicateCollaboratorRole',
                'info'    => "Request could not be completed because collaborator already has roles: [{$roleNames}]"
            ]);
        }
    }
}
