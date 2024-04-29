<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Models\Folder;
use App\Models\MutedCollaborator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class RemoveCollaboratorFromMutedCollaboratorsList implements Scope
{
    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect([
            'affectedCollaboratorIsMuted' => MutedCollaborator::query()
                ->selectRaw('1')
                ->whereColumn('folder_id', 'folders.id')
                ->whereColumn('user_id', 'collaboratorId')
        ]);
    }

    public function __invoke(Folder $result): void
    {
        if ( ! $result->affectedCollaboratorIsMuted) {
            return;
        }

        $data = [
            'folderId'       => $result->id,
            'collaboratorId' => $result->collaboratorId
        ];

        dispatch(function () use ($data) {
            MutedCollaborator::query()
                ->where('folder_id', $data['folderId'])
                ->where('user_id', $data['collaboratorId'])
                ->delete();
        })->afterResponse();
    }
}
