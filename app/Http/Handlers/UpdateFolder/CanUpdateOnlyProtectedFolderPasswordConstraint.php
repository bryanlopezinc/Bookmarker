<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CanUpdateOnlyProtectedFolderPasswordConstraint implements Scope
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect(['visibility']);
    }

    public function __invoke(Folder $folder): void
    {
        if ($this->data->isUpdatingVisibility) {
            return;
        }

        if ($this->data->isUpdatingFolderPassword && ! $folder->visibility->isPasswordProtected()) {
            throw new HttpException(
                ['message' => 'FolderNotPasswordProtected', 'info' => 'folder is not password protected'],
                400
            );
        }
    }
}
