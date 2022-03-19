<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\Models\Bookmark as Model;
use DOMXPath;

final class UpdateBookmarkTitleWithMetaTag
{
    public function __construct(private \DOMDocument $dOMDocument)
    {
    }

    public function __invoke(Bookmark $bookmark): void
    {
        if ($bookmark->hasCustomTitle) {
            return;
        }

        $DOMXPath = new DOMXPath($this->dOMDocument);

        $title = $this->getOpenGraphTagContent($DOMXPath) ?: $this->getTitleTagContent($DOMXPath);

        if ($title === false) return;

        Model::query()
            ->where('id', $bookmark->id->toInt())
            ->update(['title' => $title]);
    }

    private function getOpenGraphTagContent(DOMXPath $dOMXPath): string|bool
    {
        return $this->filter($dOMXPath->query('//meta[@name="og:title"]/@content')->item(0)?->nodeValue);
    }

    private function getTitleTagContent(DOMXPath $dOMXPath): string|bool
    {
        return $this->filter($dOMXPath->query('/html/head/title')->item(0)?->nodeValue);
    }

    private function filter(?string $value): string|bool
    {
        return blank($value) ? false : e($value);
    }
}
