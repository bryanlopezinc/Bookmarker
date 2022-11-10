<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\Resources\Json\JsonResource;

interface TransformsNotificationInterface
{
    public function toJsonResource(): JsonResource;
}