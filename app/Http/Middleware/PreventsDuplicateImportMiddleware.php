<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;

final class PreventsDuplicateImportMiddleware extends PreventsDuplicatePostRequestMiddleware
{
    #[\Override]
    protected function isSuccessful(Response $response): bool
    {
        return $response->getStatusCode() === Response::HTTP_PROCESSING;
    }
}
