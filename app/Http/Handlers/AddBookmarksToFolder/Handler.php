<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Models\Folder;
use App\Models\Bookmark;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
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
        FolderCanContainBookmarksValidator::class,
        UserOwnsBookmarksConstraint::class,
        BookmarksExistsConstraint::class,
        CollaboratorCannotMarkBookmarksAsHiddenConstraint::class,
        UniqueFolderBookmarkConstraint::class,
        CreateFolderBookmarks::class,
        SendBookmarksAddedToFolderNotification::class,
        CheckBookmarksHealth::class,
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

        $bookmarks = Bookmark::query()->findMany($bookmarkIds, ['user_id', 'id', 'url'])->all();

        $this->requestHandlersQueue->scope($query, function ($handler) use ($bookmarks) {
            if ($handler instanceof BookmarksAwareInterface) {
                $handler->setBookmarks($bookmarks);
            }
        });

        $folder = $query->findOr($folderId, callback: fn () => new Folder());

        $this->requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }
}
