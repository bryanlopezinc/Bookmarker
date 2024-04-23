<?php

declare(strict_types=1);

namespace App\Cache;

use App\DataTransferObjects\FolderInviteData;
use App\UAC;
use Illuminate\Contracts\Cache\Repository;
use OutOfBoundsException;

final class FolderInviteDataRepository
{
    public function __construct(private readonly Repository $repository, private readonly int $ttl)
    {
    }

    public function store(string $uuid, FolderInviteData $data): void
    {
        $data = [
            'inviterId'   => $data->inviterId,
            'inviteeId'   => $data->inviteeId,
            'folderId'    => $data->folderId,
            'permissions' => $data->permissions->toArray(),
            'roles'       => $data->roles
        ];

        $this->repository->put($uuid, $data, $this->ttl);
    }

    public function has(string $inviteId): bool
    {
        return $this->repository->has($inviteId);
    }

    /**
     * @throws OutOfBoundsException
     */
    public function get(string $inviteId): FolderInviteData
    {
        $payload = $this->repository->get($inviteId, []);

        if (empty($payload)) {
            throw new OutOfBoundsException("The invitation Id {$inviteId} does not exists."); // @codeCoverageIgnore
        }

        $payload['permissions'] = new UAC($payload['permissions']);

        return new FolderInviteData(...$payload);
    }
}
