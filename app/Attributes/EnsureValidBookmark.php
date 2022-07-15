<?php

declare(strict_types=1);

namespace App\Attributes;

use Attribute;
use App\Contracts\AfterDTOSetUpHookInterface;
use App\DataTransferObjects\Bookmark;

#[Attribute(Attribute::TARGET_CLASS)]
final class EnsureValidBookmark implements AfterDTOSetUpHookInterface
{
    /**
     * @param Bookmark $bookmark
     */
    public function executeAfterSetUp(Object $bookmark): void
    {
        $maxTagsLength = setting('MAX_BOOKMARKS_TAGS');

        if (!$bookmark->offsetExists('tags')) {
            return;
        }

        if ($bookmark->tags->count() > $maxTagsLength) {
            throw new \Exception('Bookmark cannot have more than ' . $maxTagsLength . ' tags', 600);
        }
    }
}
