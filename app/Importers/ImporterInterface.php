<?php

declare(strict_types=1);

namespace App\Importers;

use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;

interface ImporterInterface
{
    public function import(UserID $userID, Uuid $requestID, array $requestData): void;
}
