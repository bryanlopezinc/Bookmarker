<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DOMReader;

final class UpdateBookmarkImageUrlWithMetaTag
{
    public function __construct(private DOMReader $dOMReader)
    {
    }

    public function __invoke(Bookmark $bookmark): void
    {
        $url = $this->dOMReader->getPreviewImageUrl();

        if ($url === false) {
            return;
        }

        Model::query()
            ->where('id', $bookmark->id->toInt())
            ->update(['preview_image_url' => $url->value]);
    }
}
