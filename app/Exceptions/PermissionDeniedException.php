<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class PermissionDeniedException extends RuntimeException
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
            Permission::UPDATE_FOLDER    => 'NoUpdatePermission',
            Permission::DELETE_BOOKMARKS => 'NoRemoveBookmarksPermission',
            Permission::ADD_BOOKMARKS    => 'NoAddBookmarkPermission',
            Permission::INVITE_USER      => 'NoSendInvitePermission'
        };

        return new JsonResponse(['message' => $message], JsonResponse::HTTP_FORBIDDEN);
    }
}
