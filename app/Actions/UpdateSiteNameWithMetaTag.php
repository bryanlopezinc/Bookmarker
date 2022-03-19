<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\Models\WebSite;
use DOMXPath;

final class UpdateSiteNameWithMetaTag
{
    public function __construct(private \DOMDocument $DOMDocument)
    {
    }

    public function __invoke(Bookmark $bookmark): void
    {
        $DOMXPAth = new DOMXPath($this->DOMDocument);

        $sitename = $this->getMetaTagContent($DOMXPAth);

        if ($sitename === false || blank($sitename)) {
            return;
        }

        $site = WebSite::query()->where('id', $bookmark->fromWebSite->id->toInt())->first(['name', 'id', 'host']);

        if (!$bookmark->fromWebSite->nameHasBeenUpdated) {
            $site->update([
                'name' => $sitename,
                'name_updated_at' => now()
            ]);
        }
    }

    private function getMetaTagContent(DOMXPath $DOMXPath): string|false
    {
        $DOMNodeList = $DOMXPath->query('//meta[@name="og:site_name"]/@content');

        if (!$DOMNodeList->length) {
            $DOMNodeList = $DOMXPath->query('//meta[@name="application-name"]/@content');
        }

        if (!$DOMNodeList->length) return false;

        return e($DOMNodeList->item(0)->nodeValue);
    }
}
