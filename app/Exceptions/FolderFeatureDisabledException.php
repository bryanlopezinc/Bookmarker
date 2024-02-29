<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class FolderFeatureDisabledException extends RuntimeException
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
            ['message' => 'FolderFeatureDisAbled', 'info' => 'Request could not be completed because this feature has been disabled by folder owner.'],
            JsonResponse::HTTP_FORBIDDEN
        );
    }
}
