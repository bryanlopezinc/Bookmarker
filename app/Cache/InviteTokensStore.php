<?php

declare(strict_types=1);

namespace App\Cache;

use App\UAC;
use Illuminate\Contracts\Cache\Repository;

final class InviteTokensStore
{
    public const INVITER_ID  = 'inviterID';
    public const INVITEE_ID  = 'inviteeID';
    public const FOLDER_ID   = 'folderID';
    public const PERMISSIONS = 'permission';

    public function __construct(private readonly Repository $repository, private readonly int $ttl)
    {
    }

    public function store(
        string $uuid,
        int $inviterID,
        int $inviteeID,
        int $folderID,
        UAC $permissions
    ): void {

        $data = [
            'inviterId'   => $inviterID,
            'inviteeId'   => $inviteeID,
            'folderId'    => $folderID,
            'permissions' => $permissions->toArray()
        ];

        $this->repository->put($uuid, $data, $this->ttl);
    }

    /**
     * @return array{
     *   inviterId: int,
     *   inviteeId: int,
     *   folderId: int,
     *   permissions: string[]
     *  }
     */
    public function get(string $token): array
    {
        return $this->repository->get($token, []);
    }
}
