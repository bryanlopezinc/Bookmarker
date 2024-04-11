<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CanUpdateOnlyProtectedFolderPasswordConstraint implements FolderRequestHandlerInterface, Scope
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

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if ($this->data->isUpdatingVisibility) {
            return;
        }

        if ($this->data->folderPasswordIsSet && ! $folder->visibility->isPasswordProtected()) {
            throw new HttpException(
                ['message' => 'FolderNotPasswordProtected', 'info' => 'folder is not password protected'],
                400
            );
        }
    }
}
