<?php

declare(strict_types=1);

namespace App\Actions\AcceptFolderInvite;

use App\Exceptions\AcceptFolderInviteException;
use App\Models\Folder;
use App\Models\Scopes\WhereFolderOwnerExists;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class FolderExistConstraint implements HandlerInterface, Scope
{
    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->tap(new WhereFolderOwnerExists());
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if (!$folder->exists) {
            throw AcceptFolderInviteException::dueToFolderNotFound();
        }
    }
}
