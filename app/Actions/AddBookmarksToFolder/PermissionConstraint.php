<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Exceptions\AddBookmarksToFolderException;
use App\Models\Folder;
use App\Models\User;
use App\Repositories\Folder\CollaboratorPermissionsRepository as PermissionsRepository;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

final class PermissionConstraint implements HandlerInterface, Scope
{
    private readonly PermissionsRepository $permissions;
    private readonly User $authUser;

    public function __construct(User $authUser, PermissionsRepository $permissions = null)
    {
        $this->permissions = $permissions ?: new PermissionsRepository();
        $this->authUser = $authUser;
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->addSelect(['user_id']);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder, array $bookmarkIds): void
    {
        $authUserId = $this->authUser->id;

        if ($folder->user_id === $authUserId) {
            return;
        }

        $userPermissions = $this->permissions->all($authUserId, $folder->id);

        if ($userPermissions->isEmpty()) {
            throw AddBookmarksToFolderException::folderNotFound();
        }

        if (!$userPermissions->canAddBookmarks()) {
            throw AddBookmarksToFolderException::permissionDenied();
        }
    }
}
