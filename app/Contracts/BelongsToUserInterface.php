<?php

declare(strict_types=1);

namespace App\Contracts;

use App\ValueObjects\UserID;

interface BelongsToUserInterface
{
    public function getOwnerID(): UserID;
}