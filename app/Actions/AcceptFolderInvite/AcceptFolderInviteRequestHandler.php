<?php

declare(strict_types=1);

namespace App\Actions\AcceptFolderInvite;

use App\Cache\FolderInviteDataRepository;
use App\Exceptions\AcceptFolderInviteException;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Scope;

final class AcceptFolderInviteRequestHandler
{
    /** @var array<class-string,HandlerInterface> */
    private array $requestHandlersQueue = [];
    private readonly FolderInviteDataRepository $folderInviteDataRepository;

    public function __construct(FolderInviteDataRepository $inviteTokensStore = null)
    {
        $this->folderInviteDataRepository = $inviteTokensStore ?: app(FolderInviteDataRepository::class);
    }

    public function queue(HandlerInterface $handler): void
    {
        if (array_key_exists($key = $handler::class, $this->requestHandlersQueue)) {
            throw new \Exception("Handler [{$key}] has already been queued.");
        }

        $this->requestHandlersQueue[$handler::class] = $handler;
    }

    /**
     * @throws AcceptFolderInviteException
     */
    public function handle(string $inviteId): void
    {
        //Make a least one select to prevent fetching all columns
        //as other handlers would use addSelect() ideally.
        $query = Folder::query()->select(['id']);

        if (empty($this->requestHandlersQueue)) {
            throw new \LogicException("A handler has not been set for the request.");
        }

        if (!$this->folderInviteDataRepository->has($inviteId)) {
            throw AcceptFolderInviteException::dueToExpiredOrInvalidInvitationToken();
        }

        $invitationData = $this->folderInviteDataRepository->get($inviteId);

        foreach ($this->requestHandlersQueue as $handler) {
            if ($handler instanceof FolderInviteDataAwareInterface) {
                $handler->setInvitationData($invitationData);
            }

            if ($handler instanceof Scope) {
                $handler->apply($query, $query->getModel());
            }
        }

        $folder = $query->findOr($invitationData->folderId, callback: fn () => new Folder());

        foreach ($this->requestHandlersQueue as $handler) {
            $handler->handle($folder);
        }
    }
}
