<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\Models\WebSite;
use App\Readers\WebPageData;

final class UpdateSiteNameWithWebPageSiteName
{
    public function __construct(private WebPageData $pageData)
    {
    }

    public function __invoke(Bookmark $bookmark): void
    {
        $sitename = $this->pageData->hostSiteName;

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
}
