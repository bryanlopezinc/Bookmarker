<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\DataTransferObjects\FolderInviteData;

interface InvitationDataAwareInterface
{
    public function setInvitationData(FolderInviteData $payload): void;
}
