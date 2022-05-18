<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\Readers\WebPageData;

final class UpdateBookmarkThumbnailWithWebPageImage
{
    public function __construct(private WebPageData $pageData)
    {
    }

    public function __invoke(Bookmark $bookmark): void
    {
        $url = $this->pageData->thumbnailUrl;

        if ($url === false) {
            return;
        }

        Model::query()
            ->where('id', $bookmark->id->toInt())
            ->update(['preview_image_url' => $url->value]);
    }
}
