<?php

declare(strict_types=1);

namespace App\Http\Handlers\FetchFolderBookmarks;

use App\DataTransferObjects\FetchFolderBookmarksRequestData as Data;
use App\Exceptions\FolderNotFoundException;
use App\Http\Handlers\HasHandlersInterface;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class VisibilityConstraint implements Scope, HasHandlersInterface
{
    public function __construct(private readonly Data $data, private array $next)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['visibility']);
    }

    /**
     * @inheritdoc
     */
    public function getHandlers(): array
    {
        return $this->next;
    }

    public function __invoke(Folder $folder): void
    {
        $isLoggedIn = $this->data->authUser->exists;

        $folderBelongsToAuthUser = $isLoggedIn && $folder->wasCreatedBy($this->data->authUser);

        if ($folder->visibility->isPublic() || $folderBelongsToAuthUser) {
            $this->next = [];

            return;
        }

        if ($folder->visibility->isPrivate() || ! $isLoggedIn) {
            throw new FolderNotFoundException();
        }
    }
}
