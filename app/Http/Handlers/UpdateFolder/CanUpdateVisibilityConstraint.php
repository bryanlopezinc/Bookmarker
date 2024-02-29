<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CanUpdateVisibilityConstraint implements FolderRequestHandlerInterface, Scope
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->addSelect(['visibility']);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->data->authUser->id;

        if ($folderBelongsToAuthUser) {
            return;
        }

        if ($this->data->visibility !== null) {
            throw HttpException::forbidden([
                'message' => 'CannotUpdateFolderPrivacy',
                'info' => 'The request could not be completed due to inadequate permission.'
            ]);
        }
    }
}
