<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\ValueObjects\InviteId;

interface HandlerInterface
{
    public function handle(InviteId $inviteId): void;
}
