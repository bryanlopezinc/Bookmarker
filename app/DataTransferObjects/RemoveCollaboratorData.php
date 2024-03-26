<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;

final class RemoveCollaboratorData
{
    public function __construct(
        public readonly int $collaboratorId,
        public readonly int $folderId,
        public readonly bool $ban,
        public readonly User $authUser
    ) {
    }

    /**
     * @return array{
     *  collaboratorId: int,
     *  folderId: int,
     *  ban: bool,
     *  authUser: User
     * }
     */
    public function toArray(): array
    {
        return [
            'collaboratorId' => $this->collaboratorId,
            'folderId'       => $this->folderId,
            'ban'            => $this->ban,
            'authUser'       => $this->authUser
        ];
    }
}
