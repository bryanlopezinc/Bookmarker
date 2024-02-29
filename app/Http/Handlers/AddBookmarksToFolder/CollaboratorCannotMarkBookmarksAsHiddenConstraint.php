<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;
use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Http\Response;

final class CollaboratorCannotMarkBookmarksAsHiddenConstraint implements FolderRequestHandlerInterface
{
    public function __construct(private readonly Data $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->data->authUser->id;

        if (empty($this->data->makeHidden) || $folderBelongsToAuthUser) {
            return;
        }

        throw new HttpException([
            'message' => 'CollaboratorCannotMakeBookmarksHidden',
            'info' => 'Folder collaborator cannot mark bookmarks as hidden.'
        ], Response::HTTP_BAD_REQUEST);
    }
}
