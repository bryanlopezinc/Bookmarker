<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Enums\Permission;
use App\Exceptions\AddBookmarksToFolderException;
use App\Models\Folder;
use App\Models\Scopes\DisabledFeatureScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

final class FeatureMustBeEnabledConstraint implements HandlerInterface, Scope
{
    private readonly User $authUser;

    public function __construct(User $authUser)
    {
        $this->authUser = $authUser;
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->tap(new DisabledFeatureScope(Permission::ADD_BOOKMARKS));
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder, array $bookmarkIds): void
    {
        if ($folder->user_id === $this->authUser->id) {
            return;
        }

        if ($folder->featureIsDisabled) {
            throw AddBookmarksToFolderException::featureIsDisabled();
        }
    }
}
