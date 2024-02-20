<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Models\Folder;
use App\Models\Bookmark;
use Illuminate\Database\Eloquent\Scope;
use App\Actions\Concerns\ValidatesRequestHandler;

final class RequestHandler
{
    use ValidatesRequestHandler;

    /** @var array<class-string,HandlerInterface> */
    private array $requestHandlersQueue = [];

    public function queue(HandlerInterface $handler): void
    {
        $this->assertHandlersAreUnique($handler, $this->requestHandlersQueue);

        $this->requestHandlersQueue[$handler::class] = $handler;
    }

    /**
     * @param array<int> $bookmarkIds
     */
    public function handle(array $bookmarkIds, int $folderId): void
    {
        $this->assertHandlersQueueIsNotEmpty($this->requestHandlersQueue);

        $query = Folder::query()->select(['id']);

        $bookmarks = Bookmark::query()->findMany($bookmarkIds, ['user_id', 'id', 'url'])->all();

        foreach ($this->requestHandlersQueue as $handler) {
            if ($handler instanceof Scope) {
                $handler->apply($query, $query->getModel());
            }

            if ($handler instanceof BookmarksAwareInterface) {
                $handler->setBookmarks($bookmarks);
            }
        }

        $folder = $query->findOr($folderId, callback: fn () => new Folder());

        foreach ($this->requestHandlersQueue as $handler) {
            $handler->handle($folder, $bookmarkIds);
        }
    }
}
