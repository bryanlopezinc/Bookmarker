<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Contracts\FolderRequestHandlerInterface;
use App\Enums\Permission;
use App\Exceptions\FolderFeatureDisabledException;
use App\Models\Folder;
use App\Models\Scopes\DisabledFeatureScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

final class FeatureMustBeEnabledConstraint implements Scope, FolderRequestHandlerInterface
{
    public function __construct(private readonly User $authUser, private readonly Permission $feature)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->tap(new DisabledFeatureScope($this->feature));
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->authUser->id;

        if ($folderBelongsToAuthUser) {
            return;
        }

        if ($folder->featureIsDisabled) {
            throw new FolderFeatureDisabledException();
        }
    }
}
