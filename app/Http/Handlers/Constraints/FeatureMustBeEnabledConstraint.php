<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Enums\Feature;
use App\Exceptions\FolderFeatureDisabledException;
use App\Models\{Folder, FolderDisabledFeature, FolderFeature, User};
use Illuminate\Database\Eloquent\{Scope, Builder, Model};
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class FeatureMustBeEnabledConstraint implements Scope
{
    private readonly User $authUser;
    private readonly Collection $features;

    /**
     * @param Feature|array<Feature> $feature
     */
    public function __construct(User $authUser, Feature|array $feature)
    {
        $this->authUser = $authUser;
        $this->features = collect(Arr::wrap($feature))->ensure(Feature::class)->pluck('value');
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->withCasts(['folderDisabledFeatures' => 'collection']);

        if ($this->features->isEmpty()) {
            return;
        }

        $builder->addSelect([
            'folderDisabledFeatures' => FolderFeature::query()
                ->selectRaw('JSON_ARRAYAGG(name)')
                ->whereIn(
                    'id',
                    FolderDisabledFeature::select(['feature_id'])->whereColumn('folder_id', $model->getQualifiedKeyName()),
                )
        ]);
    }

    public function __invoke(Folder $folder): void
    {
        /** @var Collection */
        $folderDisabledFeatures = $folder->folderDisabledFeatures ?? new Collection();

        if ($this->authUser->exists && $folder->wasCreatedBy($this->authUser)) {
            return;
        }

        $folderDisabledFeatures->intersect($this->features)->each(function (string $feature) {
            throw new FolderFeatureDisabledException(Feature::from($feature));
        });
    }
}
