<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\UpdateBookmarkRepositoryInterface as Repository;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\BookmarkDescription;
use Illuminate\Support\Str;

final class UpdateBookmarkDescriptionWithWebPageDescription
{
    private Repository $repository;

    public function __construct(private BookmarkMetaData $pageData, Repository $repository = null)
    {
        $this->repository = $repository ?? app(Repository::class);
    }

    public function __invoke(Bookmark $bookmark): void
    {
        if ($bookmark->descriptionWasSetByUser) {
            return;
        }

        $description = $this->pageData->description;

        if ($description === false) {
            return;
        }

        //subtract 3 from MAX_LENGTH to accomodate the 'end' (...) value
        $newDescription = Str::limit($description, BookmarkDescription::MAX_LENGTH - 3);

        $this->repository->update(
            UpdateBookmarkDataBuilder::new()
                ->id($bookmark->id->toInt())
                ->description($newDescription)
                ->build()
        );
    }
}
