<?php

declare(strict_types=1);

namespace App\Importing\Http\Middleware;

use App\Http\Middleware\PreventsDuplicatePostRequestMiddleware;
use Symfony\Component\HttpFoundation\Response;

final class PreventsDuplicateImportMiddleware extends PreventsDuplicatePostRequestMiddleware
{
    #[\Override]
    protected function isSuccessful(Response $response): bool
    {
        return $response->getStatusCode() === Response::HTTP_PROCESSING;
    }
}
