<?php

declare(strict_types=1);

namespace App\Actions\AcceptFolderInvite;

use App\Exceptions\AcceptFolderInviteException;
use App\Models\Folder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class VisibilityConstraint implements HandlerInterface, Scope
{
    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->addSelect(['visibility']);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $visibility = $folder->visibility;

        if ($visibility->isPublic() || $visibility->isVisibleToCollaboratorsOnly()) {
            return;
        }

        if ($visibility->isPrivate()) {
            throw AcceptFolderInviteException::dueToPrivateFolder();
        }

        if ($visibility->isPasswordProtected()) {
            throw AcceptFolderInviteException::dueToPasswordProtectedFolder();
        }
    }
}
