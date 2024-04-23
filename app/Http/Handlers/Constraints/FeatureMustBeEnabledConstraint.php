<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Enums\Feature;
use App\Exceptions\FolderFeatureDisabledException;
use App\Models\Folder;
use App\Models\FolderDisabledFeature;
use App\Models\FolderFeature;
use App\Models\User;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class FeatureMustBeEnabledConstraint implements Scope
{
    public function __construct(private readonly ?User $authUser, private readonly Feature $feature)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect([
            'featureIsDisabled' => FolderDisabledFeature::query()
                ->selectRaw('1')
                ->whereColumn('folder_id', 'folders.id')
                ->whereIn('feature_id', FolderFeature::select('id')->where('name', $this->feature->value))
        ]);
    }

    public function __invoke(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->authUser?->id;

        if ($folderBelongsToAuthUser) {
            return;
        }

        if ($folder->featureIsDisabled) {
            throw new FolderFeatureDisabledException();
        }
    }
}
