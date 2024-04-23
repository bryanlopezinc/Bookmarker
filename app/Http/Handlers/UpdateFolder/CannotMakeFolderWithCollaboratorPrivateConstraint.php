<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\FolderVisibility;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderCollaborator;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CannotMakeFolderWithCollaboratorPrivateConstraint implements Scope
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        if ( ! $this->data->isUpdatingVisibility) {
            return;
        }

        //we could query for collaborators count but no need to count
        //rows when we could do a simple select.
        $builder->addSelect([
            'hasCollaborators' => FolderCollaborator::query()
                ->select('id')
                ->whereColumn('folder_id', 'folders.id')
                ->whereExists(User::whereRaw('id = folders_collaborators.collaborator_id'))
                ->limit(1)
        ]);
    }

    public function __invoke(Folder $folder): void
    {
        if ( ! $this->data->isUpdatingVisibility) {
            return;
        }

        $newVisibility = FolderVisibility::fromRequest($this->data->visibility);

        if ($folder->hasCollaborators && ($newVisibility->isPrivate() || $newVisibility->isPasswordProtected())) {
            throw HttpException::forbidden([
                'message' => 'CannotMakeFolderWithCollaboratorsPrivate',
                'info' => 'The request could not be completed because a folder with collaborators cannot be made private or password protected.'
            ]);
        }
    }
}
