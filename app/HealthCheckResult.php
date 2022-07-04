<?php

declare(strict_types=1);

namespace App;

use App\ValueObjects\ResourceID;
use Illuminate\Http\Client\Response;

final class HealthCheckResult
{
    public function __construct(
        public readonly ResourceID $bookmarkID,
        public readonly Response $response
    ) {
    }
}
