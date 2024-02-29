<?php

declare(strict_types=1);

namespace App\Contracts;

interface AcceptFolderInviteRequestHandlerInterface
{
    public function handle(string $inviteId): void;
}
