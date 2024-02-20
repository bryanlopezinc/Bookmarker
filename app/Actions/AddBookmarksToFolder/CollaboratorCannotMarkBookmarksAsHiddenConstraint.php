<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Exceptions\AddBookmarksToFolderException;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\Request;

final class CollaboratorCannotMarkBookmarksAsHiddenConstraint implements HandlerInterface
{
    private readonly Request $request;
    private readonly User $authUser;

    public function __construct(User $authUser, Request $request = null,)
    {
        $this->request = $request ?: app('request');
        $this->authUser = $authUser;
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder, array $bookmarkIds): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->authUser->id;

        if ($this->request->missing('make_hidden') || $folderBelongsToAuthUser) {
            return;
        }

        throw AddBookmarksToFolderException::cannotMarkBookmarksAsHidden();
    }
}
