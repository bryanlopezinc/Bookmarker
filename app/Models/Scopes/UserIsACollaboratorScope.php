<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\FolderCollaborator;
use App\Models\User;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UserIsACollaboratorScope implements Scope
{
    public function __construct(
        private readonly int|UserPublicId|string $userId,
        private readonly string $as = 'userIsACollaborator'
    ) {
    }

    public function __invoke(Builder $query): void
    {
        $query
            ->withCasts([$this->as => 'boolean'])
            ->addSelect([$this->as => $this->getQuery()]);
    }

    private function getQuery(): Builder
    {
        $userIsACollaboratorQuery = FolderCollaborator::query()
            ->selectRaw('1')
            ->whereColumn('folder_id', 'folders.id');

        if ($this->userId instanceof UserPublicId) {
            $userIsACollaboratorQuery->where(
                'collaborator_id',
                User::select('id')->tap(new WherePublicIdScope($this->userId))
            );
        }

        if (is_int($this->userId)) {
            $userIsACollaboratorQuery->where('collaborator_id', $this->userId);
        }

        if (is_string($this->userId)) {
            $userIsACollaboratorQuery->whereColumn('collaborator_id', $this->userId);
        }

        return $userIsACollaboratorQuery;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder, Model $model)
    {
        $this($builder);
    }
}
