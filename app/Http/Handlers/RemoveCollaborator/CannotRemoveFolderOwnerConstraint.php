<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\DataTransferObjects\RemoveCollaboratorData as Data;
use App\Models\User;
use App\ValueObjects\PublicId\UserPublicId;

final class CannotRemoveFolderOwnerConstraint implements Scope
{
    public function __construct(private readonly Data $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect([
            'folderOwnerPublicId' => User::select('public_id')->whereColumn('id', 'folders.user_id')
        ]);
    }

    public function __invoke(Folder $folder): void
    {
        if ($this->data->collaboratorId->equals(new UserPublicId($folder->folderOwnerPublicId))) {
            throw HttpException::forbidden([
                'message' => 'CannotRemoveFolderOwner',
                'info'    => ''
            ]);
        }
    }
}
