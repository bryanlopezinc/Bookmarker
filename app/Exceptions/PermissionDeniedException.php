<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class PermissionDeniedException extends RuntimeException
{
    public function report(): void
    {
    }

    /**
     * Render the exception into an HTTP Response.
     */
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(
            ['message' => 'PermissionDenied', 'info' => 'Request could not be completed because the user does not have required permission.'],
            JsonResponse::HTTP_FORBIDDEN
        );
    }
}
