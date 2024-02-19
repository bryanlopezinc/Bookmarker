<?php

declare(strict_types=1);

namespace App\Actions\AcceptFolderInvite;

use App\DataTransferObjects\FolderInviteData;

interface FolderInviteDataAwareInterface
{
    public function setInvitationData(FolderInviteData $payload): void;
}
