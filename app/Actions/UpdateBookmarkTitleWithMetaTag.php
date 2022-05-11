<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\Models\Bookmark as Model;
use App\Readers\WebPageData;

final class UpdateBookmarkTitleWithMetaTag
{
    public function __construct(private WebPageData $pageData)
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
            ->update(['title' => $title]);
    }
}
