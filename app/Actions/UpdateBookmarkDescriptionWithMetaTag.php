<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\DOMReader;
use App\Models\Bookmark as Model;

final class UpdateBookmarkDescriptionWithMetaTag
{
    public function __construct(private DOMReader $dOMReader)
    {
    }

    public function __invoke(Bookmark $bookmark): void
    {
        if ($bookmark->descriptionWasSetByUser) {
            return;
        }

        $description = $this->dOMReader->getPageDescription();

        if ($description === false) {
            return;
        }

        Model::query()->where('id', $bookmark->id->toInt())->update(['description' => $description]);
    }
}