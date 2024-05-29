<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\FolderInviteData;
use App\UAC;
use App\ValueObjects\InviteId;
use Illuminate\Contracts\Cache\Repository;
use OutOfBoundsException;

final class FolderInviteDataRepository
{
    public function __construct(private readonly Repository $repository, private readonly int $ttl)
    {
    }

    public function store(InviteId $inviteId, FolderInviteData $data): void
    {
        $data = [
            'inviterId'   => $data->inviterId,
            'inviteeId'   => $data->inviteeId,
            'folderId'    => $data->folderId,
            'permissions' => $data->permissions->toArray(),
            'roles'       => $data->roles
        ];

        $this->repository->put($inviteId->value, $data, $this->ttl);
    }

    public function has(InviteId $inviteId): bool
    {
        return $this->repository->has($inviteId->value);
    }

    /**
     * @throws OutOfBoundsException
     */
    public function get(InviteId $inviteId): FolderInviteData
    {
        $payload = $this->repository->get($inviteId->value, []);

        if (empty($payload)) {
            throw new OutOfBoundsException("The invitation Id {$inviteId->value} does not exists."); // @codeCoverageIgnore
        }

        $payload['permissions'] = new UAC($payload['permissions']);

        return new FolderInviteData(...$payload);
    }
}
