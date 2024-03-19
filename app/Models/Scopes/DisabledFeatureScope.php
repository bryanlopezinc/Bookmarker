<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\Feature;
use App\Models\Folder;
use App\Models\FolderDisabledFeature;
use App\Repositories\Folder\FeaturesRepository;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class DisabledFeatureScope implements Scope
{
    private readonly FeaturesRepository $repository;
    private readonly ?Feature $feature;
    private readonly string $alias;

    public function __construct(
        ?Feature $feature = null,
        string $alias = 'featureIsDisabled',
        FeaturesRepository $repository = null
    ) {
        $this->repository = $repository ?? new FeaturesRepository();
        $this->feature = $feature;
        $this->alias = $alias;
    }

    public function __invoke(Builder|QueryBuilder $query): void
    {
        $folderModel = new Folder();

        $query->addSelect([
            $this->alias => FolderDisabledFeature::query()
                ->select('id')
                ->whereRaw("folder_id = {$folderModel->getQualifiedKeyName()}")
                ->when($this->feature, fn ($query) => $query->where('feature_id', $this->repository->findByName($this->feature)->id)) //@phpstan-ignore-line
                ->when( ! $this->feature, fn ($query) => $query->limit(1))
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder, Model $model)
    {
        $this($builder);
    }
}
