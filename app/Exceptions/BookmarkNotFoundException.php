<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Bookmark;
use App\ValueObjects\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class BookmarkNotFoundException extends RuntimeException
{
    public function __construct(
        string $message = 'BookmarkNotFound',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function report(): void
    {
    }

    /**
     * @throws self
     */
    public static function throwIfDoesNotBelongToAuthUser(Bookmark $bookmark): void
    {
        if ($bookmark->user_id !== UserId::fromAuthUser()->value()) {
            throw new self;
        }
    }

    /**
     * Render the exception into an HTTP Response.
     */
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->message], JsonResponse::HTTP_NOT_FOUND);
    }
}
