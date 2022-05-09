<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\DOMReader;
use App\Models\Bookmark as Model;

final class UpdateBookmarkTitleWithMetaTag
{
    public function __construct(private DOMReader $dOMReader)
    {
    }

    public function __invoke(Bookmark $bookmark): void
    {
        if ($bookmark->hasCustomTitle) {
            return;
        }

        $title = $this->dOMReader->getPageTitle();

        if ($title === false) return;

        Model::query()
            ->where('id', $bookmark->id->toInt())
            ->update(['title' => $title]);
    }
}
