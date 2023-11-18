<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * All user folders are not deleted immediately when user deletes account but are deleted by
 * background tasks. This statement exists to ensure actions won't be performed on folders that
 * belongs to a deleted user account.
 */
final class WhereFolderOwnerExists implements Scope
{
    public function __invoke(Builder|QueryBuilder $query): void
    {
        $query->whereExists(function (&$query) {
            $query = User::select('id')->whereRaw('id = folders.user_id')->getQuery();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder, Model $model)
    {
        $this($builder);
    }
}
