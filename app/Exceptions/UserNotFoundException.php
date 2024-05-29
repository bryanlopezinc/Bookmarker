<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ResourceNotFoundExceptionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class UserNotFoundException extends RuntimeException implements ResourceNotFoundExceptionInterface
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
            ['message' => 'UserNotFound'],
            JsonResponse::HTTP_NOT_FOUND
        );
    }
}
