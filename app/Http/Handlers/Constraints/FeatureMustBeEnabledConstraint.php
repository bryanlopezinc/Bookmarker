<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Enums\Feature;
use App\Exceptions\FolderFeatureDisabledException;
use App\Models\{Folder, FolderDisabledFeature, FolderFeature, User};
use Illuminate\Database\Eloquent\{Scope, Builder, Model};
use Illuminate\Support\Arr;
use Closure;

final class FeatureMustBeEnabledConstraint implements Scope
{
    private readonly User $authUser;

    /** @var string[] */
    private readonly array $features;

    /**
     * @param Feature|array<Feature> $feature
     */
    public function __construct(?User $authUser, Feature|array $feature)
    {
        $this->authUser = $authUser ?? new User();
        $this->features = array_column(Arr::wrap($feature), 'value');
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($this->features === []) {
            return;
        }

        $builder
            ->withCasts(['FolderDisabledFeatures' => 'collection'])
            ->addSelect([
                'FolderDisabledFeatures' => FolderFeature::query()
                    ->selectRaw('JSON_ARRAYAGG(name)')
                    ->whereIn(
                        values: FolderDisabledFeature::select(['feature_id'])->whereColumn('folder_id', $model->getQualifiedKeyName()),
                        column: 'id',
                    )
            ]);
    }

    public function __invoke(Folder $folder): void
    {
        /** @var Closure(): \Illuminate\Support\Collection */
        $disabledFeatures = fn () => $folder->FolderDisabledFeatures ?? collect();

        if ($this->features === []) {
            return;
        }

        if ($this->authUser->exists && $folder->wasCreatedBy($this->authUser)) {
            return;
        }

        if ($disabledFeatures()->intersect($this->features)->isNotEmpty()) {
            throw new FolderFeatureDisabledException();
        }
    }
}
