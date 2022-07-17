<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\UpdateBookmarkRepositoryInterface as Repository;
use App\Contracts\UrlHasherInterface;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder as Builder;
use App\Readers\BookmarkMetaData;

final class UpdateBookmarkCanonicalUrlWithWebPageData
{
    private Repository $repository;
    private UrlHasherInterface $urlHasher;
    private BookmarkMetaData $pageData;

    public function __construct(
        BookmarkMetaData $pageData,
        Repository $repository = null,
        UrlHasherInterface $urlHasher = null
    ) {
        $this->pageData = $pageData;
        $this->repository = $repository ?? app(Repository::class);
        $this->urlHasher = $urlHasher ?? app(UrlHasherInterface::class);
    }

    public function __invoke(Bookmark $bookmark): void
    {
        $url = $this->pageData->canonicalUrl;

        if ($url === false) {
            return;
        }

        $this->repository->update(
            Builder::new()
                ->id($bookmark->id->toInt())
                ->canonicalUrl($url)
                ->canonicalUrlHash($this->urlHasher->hashCanonicalUrl($url))
                ->build()
        );
    }
}
