<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class FolderActionDisabledException extends RuntimeException
{
    public function __construct(private readonly Permission $permission)
    {
    }

    public function report(): void
    {
    }

    /**
     * Render the exception into an HTTP Response.
     */
    public function render(Request $request): JsonResponse
    {
        $message = match ($this->permission) {
            Permission::ADD_BOOKMARKS    => 'AddBookmarksActionDisabled',
            Permission::DELETE_BOOKMARKS => 'RemoveBookmarksActionDisabled',
            Permission::INVITE_USER      => 'InviteUserActionDisabled',
            Permission::UPDATE_FOLDER    => 'UpdateFolderActionDisabled'
        };

        return new JsonResponse(['message' => $message], JsonResponse::HTTP_FORBIDDEN);
    }
}
