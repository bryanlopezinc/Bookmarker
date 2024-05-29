<?php

declare(strict_types=1);

namespace App\Http\Handlers\SuspendCollaborator;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CannotSuspendSelfConstraint implements Scope
{
    public function __construct(private User $authUser)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['user_id']);
    }

    public function __invoke(Folder $result): void
    {
        if ($result->collaboratorId === $this->authUser->id) {
            throw HttpException::forbidden(['message' => 'CannotSuspendSelf']);
        }
    }
}
