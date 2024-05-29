<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class RoleNotFoundException extends RuntimeException
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
            ['message' => 'RoleNotFound', 'info' => 'The Role could not be found.'],
            JsonResponse::HTTP_NOT_FOUND
        );
    }
}
