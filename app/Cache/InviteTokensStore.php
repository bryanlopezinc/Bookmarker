<?php

declare(strict_types=1);

namespace App\Cache;

use App\UAC;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use Illuminate\Contracts\Cache\Repository;

final class InviteTokensStore
{
    public const INVITER_ID = 'inviterID';
    public const INVITEE_ID = 'inviteeID';
    public const FOLDER_ID = 'folderID';
    public const PERMISSIONS = 'permission';

    public function __construct(private readonly Repository $repository, private readonly int $ttl)
    {
    }

    public function store(
        Uuid $token,
        UserID $inviterID,
        UserID $inviteeID,
        ResourceID $folderID,
        UAC $permissions
    ): void {
        $data = [
            self::INVITER_ID => $inviterID->value(),
            self::INVITEE_ID => $inviteeID->value(),
            self::FOLDER_ID => $folderID->value(),
            self::PERMISSIONS => $permissions->serialize()
        ];

        $this->repository->put($token->value, $data, $this->ttl);
    }

    public function get(Uuid $token): array
    {
        return $this->repository->get($token->value, []);
    }
}
