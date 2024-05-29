<?php

declare(strict_types=1);

namespace App\Http\Handlers\FetchFolderBookmarks;

use App\Models\Folder;
use App\Actions\GetFolderBookmarks as FetchFolderBookmarksAction;
use App\DataTransferObjects\FetchFolderBookmarksRequestData as Data;
use App\DataTransferObjects\FolderBookmark;
use App\Jobs\CheckBookmarksHealth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;

final class GetFolderBookmarks implements Scope
{
    private readonly Data $data;
    private readonly FetchFolderBookmarksAction $action;

    public function __construct(Data $data, FetchFolderBookmarksAction $action = null)
    {
        $this->data = $data;
        $this->action = $action ?: new FetchFolderBookmarksAction();
    }

    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['user_id', 'visibility']);
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function handle(Folder $folder): Paginator
    {
        $authUserId = null;

        if ($this->data->authUser->exists) {
            $authUserId = $this->data->authUser->id;
        }

        return $this->action->handle($authUserId, $folder, $this->data->pagination)->tap(function (Paginator $paginator) {
            $paginator->getCollection()
                ->map(fn (FolderBookmark $folderBookmark) => $folderBookmark->bookmark)
                ->tap(fn (Collection $bookmarks) => dispatch(new CheckBookmarksHealth($bookmarks)));
        });
    }
}
