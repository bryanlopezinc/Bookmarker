<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\Bookmark;
use App\PaginationData;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;
use App\Repositories\BookmarkRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;

final class FetchDuplicateBookmarksService
{
    public function __construct(private BookmarkRepository $repository)
    {
    }

    /**
     * @return Paginator<Bookmark>
     */
    public function fetch(ResourceID $bookmarkID, PaginationData $pagination): Paginator
    {
        $bookmark = $this->repository->findById($bookmarkID, BookmarkAttributes::only('url_canonical_hash,user_id,id'));

        (new EnsureAuthorizedUserOwnsResource)($bookmark);

        return $this->repository->fetchPossibleDuplicates($bookmark, UserID::fromAuthUser(), $pagination);
    }
}
