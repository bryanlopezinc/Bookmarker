<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\Models\Bookmark as Model;
use App\Readers\WebPageData;

final class UpdateBookmarkDescriptionWithMetaTag
{
    public function __construct(private WebPageData $pageData)
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

        Model::query()->where('id', $bookmark->id->toInt())->update(['description' => $description]);
    }
}