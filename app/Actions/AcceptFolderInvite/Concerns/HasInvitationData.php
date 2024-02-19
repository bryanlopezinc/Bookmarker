<?php

declare(strict_types=1);

namespace App\Actions\AcceptFolderInvite\Concerns;

use App\DataTransferObjects\FolderInviteData;

trait HasInvitationData
{
    private FolderInviteData $invitationData;

    public function setInvitationData(FolderInviteData $payload): void
    {
        $this->invitationData = $payload;
    }
}
