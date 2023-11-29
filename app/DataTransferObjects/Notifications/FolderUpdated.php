<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\Models\Folder;
use App\Models\User;

final class FolderUpdated
{
    public function __construct(
        public readonly ?Folder $folder,
        public readonly ?User $collaborator,
        public readonly array $changes,
        public readonly string $uuid,
        public readonly string $notifiedOn,
        public readonly string $modifiedAttribute
    ) {
    }
}
