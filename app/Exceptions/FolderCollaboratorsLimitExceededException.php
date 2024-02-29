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
     * Render the exception into an HTTP Response.
     */
    public function render(Request $request): JsonResponse
    {
        $data = [
            'message' => 'MaxCollaboratorsLimitReached',
            'info' => 'Folder has reached its max collaborators limit.'
        ];

        return new JsonResponse($data, JsonResponse::HTTP_FORBIDDEN);
    }
}
