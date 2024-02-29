<?php

declare(strict_types=1);

namespace App\Http\Handlers\FetchFolderBookmarks;

use App\Models\Folder;
use Illuminate\Database\Eloquent\Scope;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Http\Handlers\Constraints;
use App\DataTransferObjects\FolderBookmark;
use App\Http\Handlers\RequestHandlersQueue;
use Illuminate\Pagination\Paginator;

final class Handler
{
    /**
     * @var array<class-string<HandlerInterface>>
     */
    private const HANDLERS = [
        Constraints\FolderExistConstraint::class,
        FolderPasswordConstraint::class,
        VisibilityConstraint::class,
        Constraints\MustBeACollaboratorConstraint::class
    ];

    /**
     * @var RequestHandlersQueue<HandlerInterface>
     */
    private RequestHandlersQueue $requestHandlersQueue;

    private readonly GetFolderBookmarks $action;

    public function __construct(GetFolderBookmarks $getFolderBookmarks)
    {
        $this->requestHandlersQueue = new RequestHandlersQueue(self::HANDLERS);
        $this->action = $getFolderBookmarks;
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function handle(int $folderId): Paginator
    {
        $query = Folder::query()->select(['id']);

        foreach ([$this->action, ...$this->requestHandlersQueue] as $handler) {
            if ($handler instanceof Scope) {
                $handler->apply($query, $query->getModel());
            }
        }

        $folder = $query->findOr($folderId, callback: fn () => new Folder());

        $this->requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });

        return $this->action->handle($folder);
    }
}
