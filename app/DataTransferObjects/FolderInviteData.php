<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final class FolderInviteData
{
    /**
     * @param array<string> $permissions
     */
    public function __construct(
        public readonly int $inviterId,
        public readonly int $inviteeId,
        public readonly int $folderId,
        public readonly array $permissions
    ) {
    }
}
