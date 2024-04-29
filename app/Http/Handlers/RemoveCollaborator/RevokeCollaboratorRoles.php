<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Models\Folder;
use App\Models\FolderCollaboratorRole;
use App\Models\FolderRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class RevokeCollaboratorRoles implements Scope
{
    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->withCasts(['affectedCollaboratorHasAnAssignedRole' => 'boolean'])
            ->addSelect([
                'affectedCollaboratorHasAnAssignedRole' => FolderCollaboratorRole::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('collaborator_id', 'collaboratorId')
                    ->whereIn('role_id', FolderRole::select(['id'])->whereColumn('folder_id', 'folders.id'))
                    ->limit(1)
            ]);
    }

    public function __invoke(Folder $result): void
    {
        if ( ! $result->affectedCollaboratorHasAnAssignedRole) {
            return;
        }

        $data = [
            'folderId'       => $result->id,
            'collaboratorId' => $result->collaboratorId
        ];

        dispatch(function () use ($data) {
            FolderCollaboratorRole::query()
                ->where('collaborator_id', $data['collaboratorId'])
                ->whereIn('role_id', FolderRole::select(['id'])->where('folder_id', $data['folderId']))
                ->delete();
        })->afterResponse();
    }
}
