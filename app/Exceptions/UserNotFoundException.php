<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class UserNotFoundException extends RuntimeException
{
    public function report(): void
    {
    }

    /**
     * @throws self
     */
    public static function throwIf(bool $condition): void
    {
        if ($condition) {
            throw new self();
        }
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
