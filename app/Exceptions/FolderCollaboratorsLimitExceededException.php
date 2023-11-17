<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class FolderCollaboratorsLimitExceededException extends RuntimeException
{
    public function report(): void
    {
    }

    /**
     * @throws self
     */
    public static function throwIfExceeded(int $currentCollaboratorsCount): void
    {
        if ($currentCollaboratorsCount >= 1000) {
            throw new self();
        }
    }

    /**
     * Render the exception into an HTTP Response.
     */
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => 'MaxCollaboratorsLimitReached'], JsonResponse::HTTP_FORBIDDEN);
    }
}
