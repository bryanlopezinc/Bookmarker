<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\Exceptions\AcceptFolderInviteException;

final class InviterAndInviteeExistsValidator
{
    public function __construct(private readonly UserRepository $repository)
    {
    }

    public function __invoke(): void
    {
        if ( ! $this->repository->invitee()->exists) {
            throw AcceptFolderInviteException::inviteeAccountNoLongerExists();
        }

        if ( ! $this->repository->inviter()->exists) {
            throw AcceptFolderInviteException::inviterAccountNoLongerExists();
        }
    }
}
