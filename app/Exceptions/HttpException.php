<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(
        private readonly mixed $data,
        private readonly int $status
    ) {
    }

    public static function notFound(mixed $message = []): self
    {
        return new self($message, Response::HTTP_NOT_FOUND);
    }

    public static function conflict(mixed $message = []): self
    {
        return new self($message, Response::HTTP_CONFLICT);
    }

    public static function unAuthorized(mixed $message = []): self
    {
        return new self($message, Response::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(mixed $message = []): self
    {
        return new self($message, Response::HTTP_FORBIDDEN);
    }

    public function report(): void
    {
    }

    /**
     * Render the exception into an HTTP Response.
     */
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse($this->data, $this->status);
    }
}
