<?php

declare(strict_types=1);

namespace App\Http\Handlers\FetchFolderBookmarks;

use App\Contracts\FolderRequestHandlerInterface;
use App\Contracts\StopsRequestHandling;
use App\DataTransferObjects\FetchFolderBookmarksRequestData as Data;
use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class VisibilityConstraint implements FolderRequestHandlerInterface, Scope, StopsRequestHandling
{
    private bool $stopRequestHandling = false;

    public function __construct(private readonly Data $data)
    {
    }

    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['visibility']);
    }

    public function stopRequestHandling(): bool
    {
        return $this->stopRequestHandling;
    }

    public function handle(Folder $folder): void
    {
        $isLoggedIn = $this->data->authUser !== null;

        $folderBelongsToAuthUser = $folder->user_id === $this->data->authUser?->id;

        if ($folder->visibility->isPublic() || $folderBelongsToAuthUser) {
            $this->stopRequestHandling = true;

            return;
        }

        if ($folder->visibility->isPrivate() || !$isLoggedIn) {
            throw new FolderNotFoundException();
        }
    }
}
