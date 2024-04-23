<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Folder;
use App\Models\FolderCollaborator;
use App\Models\User;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UserIsACollaboratorScope implements Scope
{
    public function __construct(
        private readonly int|UserPublicId $userId,
        private readonly string $as = 'userIsACollaborator'
    ) {
    }

    public function __invoke(Builder $query): void
    {
        $folderModel = new Folder();

        $query
            ->withCasts([$this->as => 'boolean'])
            ->addSelect([
                $this->as => FolderCollaborator::query()
                    ->select('id')
                    ->whereColumn('folder_id', $folderModel->getQualifiedKeyName())
                    ->when(
                        value: $this->userId instanceof UserPublicId,
                        default: fn ($query) => $query->where('collaborator_id', $this->userId),
                        callback: function ($query) {
                            $query->where('collaborator_id', User::select('id')->where('public_id', $this->userId->value)); //@phpstan-ignore-line
                        },
                    )
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
