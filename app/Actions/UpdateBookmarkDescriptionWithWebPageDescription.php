<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\Models\Bookmark as Model;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\BookmarkDescription;
use Illuminate\Support\Str;

final class UpdateBookmarkDescriptionWithWebPageDescription
{
    public function __construct(private BookmarkMetaData $pageData)
    {
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

        Model::query()
            ->where('id', $bookmark->id->toInt())
            ->update([
                //subtract 3 from MAX_LENGTH to accomodate the 'end' (...) value
                'description' => Str::limit($description, BookmarkDescription::MAX_LENGTH - 3)
            ]);
    }
}
