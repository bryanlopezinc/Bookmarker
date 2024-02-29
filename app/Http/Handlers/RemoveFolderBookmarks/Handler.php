<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\Models\Folder;
use App\Models\Bookmark;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Models\FolderBookmark;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;

final class Handler
{
    /**
     * @var array<class-string<HandlerInterface>>
     */
    private const HANDLERS = [
        Constraints\FolderExistConstraint::class,
        Constraints\MustBeACollaboratorConstraint::class,
        Constraints\PermissionConstraint::class,
        Constraints\FeatureMustBeEnabledConstraint::class,
        FolderContainsBookmarksConstraint::class,
        DeleteFolderBookmarks::class,
        SendBookmarksRemovedFromFolderNotificationNotification::class,
    ];

    private RequestHandlersQueue $requestHandlersQueue;

    public function __construct()
    {
        $this->requestHandlersQueue = new RequestHandlersQueue(self::HANDLERS);
    }

    /**
     * @param array<int> $bookmarkIds
     */
    public function handle(array $bookmarkIds, int $folderId): void
    {
        $query = Folder::query()->select(['id']);

        $folderBookmarks = FolderBookmark::query()
            ->where('folder_id', $folderId)
            ->whereIntegerInRaw('bookmark_id', $bookmarkIds)
            ->whereExists(Bookmark::whereRaw('id = folders_bookmarks.bookmark_id'))
            ->get()
            ->all();

        $this->requestHandlersQueue->scope($query, function ($handler) use ($folderBookmarks) {
            if ($handler instanceof FolderBookmarksAwareInterface) {
                $handler->setBookmarks($folderBookmarks);
            }
        });

        $folder = $query->findOr($folderId, callback: fn () => new Folder());

        $this->requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }
}
