<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\Models\Bookmark as Model;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\BookmarkTitle;
use Illuminate\Support\Str;

final class UpdateBookmarkTitleWithWebPageTitle
{
    public function __construct(private BookmarkMetaData $pageData)
    {
    }

    public function __invoke(Bookmark $bookmark): void
    {
        if ($bookmark->hasCustomTitle) {
            return;
        }

        $title = $this->pageData->title;

        if ($title === false) return;

        Model::query()
            ->where('id', $bookmark->id->toInt())
            ->update([
                //subtract 3 from MAX_LENGTH to accomodate the 'end' (...) value
                'title' => Str::limit($title, BookmarkTitle::MAX_LENGTH - 3)
            ]);
    }
}
