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

    public function __construct(private Repository $repository)
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
            self::INVITER_ID => $inviterID->toInt(),
            self::INVITEE_ID => $inviteeID->toInt(),
            self::FOLDER_ID => $folderID->toInt(),
            self::PERMISSIONS => $permissions->serialize()
        ];

        $this->repository->put($token->value, $data, now()->addDay());
    }

    public function get(Uuid $token): array
    {
        return $this->repository->get($token->value, []);
    }
}
