<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\DataTransferObjects\RemoveCollaboratorData as Data;

final class CannotRemoveSelfConstraint implements Scope
{
    public function __construct(private readonly Data $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect(['user_id']);
    }

    public function __invoke(Folder $folder): void
    {
        $isRemovingSelf = $this->data->authUser->public_id->equals($this->data->collaboratorId);

        if ($isRemovingSelf) {
            throw HttpException::forbidden([
                'message' => 'CannotRemoveSelf',
                'info'    => ''
            ]);
        }
    }
}
