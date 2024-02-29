<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\Cache\FolderInviteDataRepository;
use App\Contracts\AcceptFolderInviteRequestHandlerInterface as HandlerInterface;
use App\Exceptions\AcceptFolderInviteException;

final class CheckForExpiredToken implements HandlerInterface
{
    private readonly HandlerInterface $handler;
    private readonly FolderInviteDataRepository $repository;

    public function __construct(HandlerInterface $handler, FolderInviteDataRepository $repository = null)
    {
        $this->handler = $handler;
        $this->repository = $repository ?: app(FolderInviteDataRepository::class);
    }

    public function handle(string $inviteId): void
    {
        if (!$this->repository->has($inviteId)) {
            throw AcceptFolderInviteException::expiredOrInvalidInvitationToken();
        }

        $this->handler->handle($inviteId);
    }
}
