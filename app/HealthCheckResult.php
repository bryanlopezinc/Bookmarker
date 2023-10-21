<?php

declare(strict_types=1);

namespace App;

use Illuminate\Http\Client\Response;

final class HealthCheckResult
{
    public function __construct(
        public readonly int $bookmarkID,
        public readonly Response $response
    ) {
    }
}
