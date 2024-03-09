<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Folder;
use App\Models\FolderCollaborator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UserIsACollaboratorScope implements Scope
{
    public function __construct(
        private readonly int $userId,
        private readonly string $as = 'userIsACollaborator'
    ) {
    }

    public function __invoke(Builder $query): void
    {
        $folderModel = new Folder();

        $query->withCasts([$this->as => 'boolean'])
            ->addSelect([
                $this->as => FolderCollaborator::select('id')
                    ->whereColumn('folder_id', $folderModel->getQualifiedKeyName())
                    ->where('collaborator_id', $this->userId)
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
