<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Folder;
use App\Models\MutedCollaborator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class IsMutedCollaboratorScope implements Scope
{
    public function __construct(
        private readonly int $userId,
        private readonly ?int $mutedBy = null,
        private readonly string $as = 'collaboratorIsMuted'
    ) {
    }

    public function __invoke(Builder|QueryBuilder $query): void
    {
        $folderModel = new Folder();

        $query->addSelect([
            $this->as => MutedCollaborator::select('id')
                ->whereRaw("folder_id = {$folderModel->getQualifiedKeyName()}")
                ->where('user_id', $this->userId)
                ->when(!$this->mutedBy, fn ($query) => $query->whereRaw("muted_by = {$folderModel->qualifyColumn('user_id')}"))
                ->when($this->mutedBy, fn ($query) => $query->where('muted_by', $this->mutedBy))
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
