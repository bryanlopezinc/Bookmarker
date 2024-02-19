<?php

declare(strict_types=1);

namespace App\Actions\AcceptFolderInvite;

use App\Exceptions\AcceptFolderInviteException;
use App\Models\Folder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CollaboratorsLimitConstraint implements HandlerInterface, Scope
{
    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->withCount('collaborators'); //@phpstan-ignore-line
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if ($folder->collaborators_count >= setting('MAX_FOLDER_COLLABORATORS_LIMIT')) {
            throw AcceptFolderInviteException::dueToFolderCollaboratorsLimitReached();
        }
    }
}
