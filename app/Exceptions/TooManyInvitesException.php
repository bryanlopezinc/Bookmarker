<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class TooManyInvitesException extends RuntimeException
{
    public function __construct(private readonly int $retryAfterSeconds)
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
            ['message' => 'TooManyRequests', 'retry-after' => $this->retryAfterSeconds],
            JsonResponse::HTTP_TOO_MANY_REQUESTS
        );
    }
}
