<?php

declare(strict_types=1);

namespace App\Http\Handlers\FetchFolderBookmarks;

use App\Models\Folder;
use App\DataTransferObjects\FetchFolderBookmarksRequestData as Data;
use App\Http\Handlers\Constraints;
use App\DataTransferObjects\FolderBookmark;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\Scopes\WherePublicIdScope;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Pagination\Paginator;

final class Handler
{
    /**
     * @return Paginator<FolderBookmark>
     */
    public function handle(FolderPublicId $folderId, Data $data): Paginator
    {
        $query = Folder::query()->select(['id'])->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data));

        $getFolderBookmarks = new GetFolderBookmarks($data);

        $requestHandlersQueue->scope($query);
        $getFolderBookmarks->apply($query, $query->getModel());

        $requestHandlersQueue->handle($folder = $query->firstOrNew());

        return $getFolderBookmarks->handle($folder);
    }

    private function getConfiguredHandlers(Data $data): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new FolderPasswordConstraint(
                $data,
                [new VisibilityConstraint($data, [new Constraints\MustBeACollaboratorConstraint($data->authUser)])]
            ),
        ];
    }
}
