<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\BookmarkTitle;
use Illuminate\Support\Str;
use App\Contracts\UpdateBookmarkRepositoryInterface as Repository;
use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder;

final class UpdateBookmarkTitleWithWebPageTitle
{
    private Repository $repository;

    public function __construct(private BookmarkMetaData $pageData, Repository $repository = null)
    {
        $this->repository = $repository ?? app(Repository::class);
    }

    public function __invoke(Bookmark $bookmark): void
    {
        if ($bookmark->hasCustomTitle) {
            return;
        }

        $title = $this->pageData->title;

        if ($title === false) return;

        //subtract 3 from MAX_LENGTH to accomodate the 'end' (...) value
        $newTitle = Str::limit($title, BookmarkTitle::MAX_LENGTH - 3);

        $this->repository->update(
            UpdateBookmarkDataBuilder::new()
                ->id($bookmark->id->toInt())
                ->title($newTitle)
                ->build()
        );
    }
}
