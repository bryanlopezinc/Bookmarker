<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\ResourceNotFoundExceptionInterface;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

final class FolderNotFoundException extends RuntimeException implements ResourceNotFoundExceptionInterface
{
    public function __construct(
        string $message = 'FolderNotFound',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function report(): void
    {
    }

    /**
     * @throws self
     */
    public static function throwIfDoesNotBelongToAuthUser(Folder $folder): void
    {
        self::throwIf( ! $folder->wasCreatedBy(auth()->user())); //@phpstan-ignore-line
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
            ['message' => $this->message, 'info' => 'The folder could not be found.'],
            JsonResponse::HTTP_NOT_FOUND
        );
    }
}
