<?php

declare(strict_types=1);

namespace App\Http\Handlers\FetchFolderBookmarks;

use App\DataTransferObjects\FetchFolderBookmarksRequestData as Data;
use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class VisibilityConstraint implements Scope
{
    public bool $stopRequestHandling = false;

    public function __construct(private readonly Data $data)
    {
    }

    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['visibility']);
    }

    public function __invoke(Folder $folder): void
    {
        $isLoggedIn = $this->data->authUser->exists;

        $folderBelongsToAuthUser = $isLoggedIn && $folder->user_id === $this->data->authUser->id;

        if ($folder->visibility->isPublic() || $folderBelongsToAuthUser) {
            $this->stopRequestHandling = true;

            return;
        }

        if ($folder->visibility->isPrivate() || ! $isLoggedIn) {
            throw new FolderNotFoundException();
        }
    }
}
