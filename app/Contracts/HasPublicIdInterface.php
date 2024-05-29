<?php

declare(strict_types=1);

namespace App\Contracts;

use App\ValueObjects\PublicId\PublicId;

interface HasPublicIdInterface
{
    public function getPublicIdentifier(): PublicId;
}
