<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\Permission;
use App\Models\Folder;
use App\Models\FolderDisabledAction;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class DisabledFeatureScope implements Scope
{
    public function __construct(
        private readonly ?Permission $permission = null,
        private readonly string $alias = 'featureIsDisabled'
    ) {
    }
    public function __invoke(Builder|QueryBuilder $query): void
    {
        $folderModel = new Folder();

        $query->addSelect([
            $this->alias => FolderDisabledAction::select('id')
                ->whereRaw("folder_id = {$folderModel->getQualifiedKeyName()}")
                ->when($this->permission, fn ($query) => $query->where('action', $this->permission?->value))
                ->when(!$this->permission, fn ($query) => $query->limit(1))
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
