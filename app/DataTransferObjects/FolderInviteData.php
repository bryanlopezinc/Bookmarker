<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\UAC;

final class FolderInviteData
{
    public function __construct(
        public readonly int $inviterId,
        public readonly int $inviteeId,
        public readonly int $folderId,
        public readonly UAC $permissions,
        public readonly array $roles
    ) {
    }
}
