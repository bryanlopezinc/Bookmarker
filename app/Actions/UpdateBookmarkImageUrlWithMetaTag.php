<?php

declare(strict_types=1);

namespace App\Actions;

use App\ValueObjects\Url;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;

final class UpdateBookmarkImageUrlWithMetaTag
{
    public function __construct(private \DOMDocument $dOMDocument)
    {
    }

    public function __invoke(Bookmark $bookmark): void
    {
        $url = $this->getOpenGraphTagContent();

        if ($url === false) {
            return;
        }

        Model::query()
            ->where('id', $bookmark->id->toInt())
            ->update(['preview_image_url' => $url->value]);
    }

    private function getOpenGraphTagContent(): Url|false
    {
        $DOMNodeList = (new \DOMXPath($this->dOMDocument))->query('//meta[@name="og:image"]/@content');

        return Url::tryFromString($DOMNodeList->item(0)?->nodeValue);
    }
}
