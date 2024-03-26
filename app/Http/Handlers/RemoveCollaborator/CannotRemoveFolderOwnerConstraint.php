<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\DataTransferObjects\RemoveCollaboratorData as Data;

final class CannotRemoveFolderOwnerConstraint implements FolderRequestHandlerInterface, Scope
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

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if ($this->data->collaboratorId === $folder->user_id) {
            throw HttpException::forbidden([
                'message' => 'CannotRemoveFolderOwner',
                'info'    => ''
            ]);
        }
    }
}
