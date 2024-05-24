<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;
use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Http\Response;

final class CollaboratorCannotMarkBookmarksAsHiddenConstraint
{
    public function __construct(private readonly Data $data)
    {
    }

    public function __invoke(Folder $folder): void
    {
        if (empty($this->data->makeHidden) || $folder->wasCreatedBy($this->data->authUser)) {
            return;
        }

        throw new HttpException([
            'message' => 'CollaboratorCannotMakeBookmarksHidden',
            'info' => 'Folder collaborator cannot mark bookmarks as hidden.'
        ], Response::HTTP_BAD_REQUEST);
    }
}
