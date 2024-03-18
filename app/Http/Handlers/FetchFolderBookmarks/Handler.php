<?php

declare(strict_types=1);

namespace App\Http\Handlers\FetchFolderBookmarks;

use App\Models\Folder;
use Illuminate\Database\Eloquent\Scope;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\DataTransferObjects\FetchFolderBookmarksRequestData as Data;
use App\Http\Handlers\Constraints;
use App\DataTransferObjects\FolderBookmark;
use App\Http\Handlers\RequestHandlersQueue;
use Illuminate\Pagination\Paginator;

final class Handler
{
    /**
     * @return Paginator<FolderBookmark>
     */
    public function handle(int $folderId, Data $data): Paginator
    {
        $query = Folder::query()->select(['id']);

        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data));
        $getFolderBookmarks = new GetFolderBookmarks($data);

        foreach ([$getFolderBookmarks, ...$requestHandlersQueue] as $handler) {
            if ($handler instanceof Scope) {
                $handler->apply($query, $query->getModel());
            }
        }

        $folder = $query->findOr($folderId, callback: fn () => new Folder());

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });

        return $getFolderBookmarks->handle($folder);
    }

    private function getConfiguredHandlers(Data $data): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new FolderPasswordConstraint($data),
            new VisibilityConstraint($data),
            new Constraints\MustBeACollaboratorConstraint($data->authUser)
        ];
    }
}
