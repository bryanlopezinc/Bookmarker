<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\UpdateBookmarkRepositoryInterface as Repository;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder as Builder;
use App\Readers\BookmarkMetaData;

final class UpdateBookmarkResolvedUrl
{
    private Repository $repository;
    private BookmarkMetaData $pageData;

    public function __construct(BookmarkMetaData $pageData, Repository $repository = null)
    {
        $this->pageData = $pageData;
        $this->repository = $repository ?? app(Repository::class);
    }

    public function __invoke(Bookmark $bookmark): void
    {
        $this->repository->update(
            Builder::new()
                ->id($bookmark->id->toInt())
                ->resolvedUrl($this->pageData->reosolvedUrl)
                ->build()
        );
    }
}
