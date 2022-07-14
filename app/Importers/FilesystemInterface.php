<?php

declare(strict_types=1);

namespace App\Importers;

use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;

interface FilesystemInterface
{
    public function put(string $contents, UserID $userID, Uuid $requestID): void;

    public function delete(UserID $userID, Uuid $requestID): void;

    public function exists(UserID $userID, Uuid $requestID): bool;

    public function get(UserID $userID, Uuid $requestID): string;
}
