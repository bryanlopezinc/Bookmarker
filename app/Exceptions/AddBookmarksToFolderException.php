<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class AddBookmarksToFolderException extends RuntimeException implements Responsable
{
    public function __construct(
        private readonly string $errorMessage,
        private readonly int $statusCode,
        private readonly string $info,
    ) {
    }

    public static function folderNotFound(): self
    {
        return new self(
            (new FolderNotFoundException())->message,
            JsonResponse::HTTP_NOT_FOUND,
            'The folder could not be found.'
        );
    }

    public static function featureIsDisabled(): self
    {
        return new self(
            'AddBookmarksActionDisabled',
            JsonResponse::HTTP_FORBIDDEN,
            'Add bookmarks feature has been disabled for this folder by the owner.'
        );
    }

    public static function permissionDenied(): self
    {
        return new self(
            'NoAddBookmarkPermission',
            JsonResponse::HTTP_FORBIDDEN,
            'Bookmarks could not be added to folder because the user does not have required permission.'
        );
    }

    public static function folderBookmarksLimitReached(): self
    {
        return new self(
            'FolderBookmarksLimitReached',
            JsonResponse::HTTP_FORBIDDEN,
            'Folder has reached its max bookmarks limit.'
        );
    }

    public static function bookmarkDoesNotBelongToUser(): self
    {
        return self::bookmarkDoesNotExist();
    }

    public static function bookmarkDoesNotExist(): self
    {
        return new self(
            (new BookmarkNotFoundException())->message,
            JsonResponse::HTTP_NOT_FOUND,
            'The given bookmarks could not be found.'
        );
    }

    public static function cannotMarkBookmarksAsHidden(): self
    {
        return new self(
            'CollaboratorCannotMakeBookmarksHidden',
            JsonResponse::HTTP_BAD_REQUEST,
            'Folder collaborator cannot mark bookmarks as hidden.'
        );
    }

    public static function bookmarksAlreadyExists(): self
    {
        return new self(
            'FolderContainsBookmarks',
            JsonResponse::HTTP_CONFLICT,
            'The given bookmarks already exists in folder.'
        );
    }

    /**
     * @inheritdoc
     */
    public function toResponse($request)
    {
        $data = [
            'message' => $this->errorMessage,
            'info' => $this->info
        ];

        return new JsonResponse($data, $this->statusCode);
    }
}
