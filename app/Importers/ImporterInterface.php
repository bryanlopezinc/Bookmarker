<?php

declare(strict_types=1);

namespace App\Importers;

interface ImporterInterface
{
    public function import(int $userID, string $requestID, array $requestData): void;
}
