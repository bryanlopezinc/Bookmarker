<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\UAC;

final class UserCollaboration
{
    public function __construct(public readonly Folder $collaboration, public readonly UAC $permissions)
    {
    }
}
