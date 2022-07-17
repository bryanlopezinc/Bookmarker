<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\Readers\BookmarkMetaData;
use App\Contracts\UpdateBookmarkRepositoryInterface as Repository;
use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder as Builder;

final class UpdateBookmarkThumbnailWithWebPageImage
{
    private Repository $repository;

    public function __construct(private BookmarkMetaData $pageData, Repository $repository = null)
    {
        $this->repository = $repository ?? app(Repository::class);
    }

    public function __invoke(Bookmark $bookmark): void
    {
        $url = $this->pageData->thumbnailUrl;

        if ($url === false) {
            return;
        }

        $this->repository->update(
            Builder::new()
                ->id($bookmark->id->toInt())
                ->previewImageUrl($url)
                ->build()
        );
    }
}
