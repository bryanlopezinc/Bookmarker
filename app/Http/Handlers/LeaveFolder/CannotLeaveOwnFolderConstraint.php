<?php

declare(strict_types=1);

namespace App\Http\Handlers\LeaveFolder;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CannotLeaveOwnFolderConstraint implements Scope
{
    public function __construct(private readonly User $authUser)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['user_id']);
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->wasCreatedBy($this->authUser)) {
            throw HttpException::forbidden(['message' => 'CannotExitOwnFolder']);
        }
    }
}
