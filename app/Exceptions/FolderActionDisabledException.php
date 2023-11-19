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
        return new JsonResponse(
            ['message' => $this->permission->asHttpExceptionMessage()],
            JsonResponse::HTTP_FORBIDDEN
        );
    }
}
