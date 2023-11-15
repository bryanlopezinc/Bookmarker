<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\Models\Folder;
use App\Models\User;

final class CollaboratorExit
{
    public function __construct(
        public readonly ?User $collaborator,
        public readonly ?Folder $folder,
        public readonly string $uuid,
        public readonly string $notifiedOn
    ) {
    }
}
