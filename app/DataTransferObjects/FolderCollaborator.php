<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;
use App\UAC;

final class FolderCollaborator
{
    public function __construct(public readonly User $user, public readonly UAC $permissions)
    {
    }
}
