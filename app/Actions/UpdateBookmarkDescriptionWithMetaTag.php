<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\Models\Bookmark as Model;
use DOMXPath;

final class UpdateBookmarkDescriptionWithMetaTag
{
    public function __construct(private \DOMDocument $dOMDocument)
    {
    }

    public function __invoke(Bookmark $bookmark): void
    {
        if ($bookmark->descriptionWasSetByUser) {
            return;
        }

        $DOMXPath = new DOMXPath($this->dOMDocument);

        $description = $this->getOpenGraphTagContent($DOMXPath) ?: $this->getDescriptionTagContent($DOMXPath);

        if ($description === false) {
            return;
        }

        Model::query()->where('id', $bookmark->id->toInt())->update(['description' => $description]);
    }

    private function getOpenGraphTagContent(DOMXPath $dOMXPath): string|false
    {
        return $this->filterValue(
            $dOMXPath->query('//meta[@name="og:description"]/@content')->item(0)?->nodeValue
        );
    }

    private function getDescriptionTagContent(DOMXPath $dOMXPath): string|false
    {
        return $this->filterValue(
            $dOMXPath->query('//meta[@name="description"]/@content')->item(0)?->nodeValue
        );
    }

    private function filterValue(?string $value): string|false
    {
        return is_null($value) ? false : e($value);
    }
}
