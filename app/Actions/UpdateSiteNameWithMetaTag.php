<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Bookmark;
use App\DOMReader;
use App\Models\WebSite;

final class UpdateSiteNameWithMetaTag
{
    public function __construct(private DOMReader $dOMReader)
    {
    }

    public function __invoke(Bookmark $bookmark): void
    {
        $sitename = $this->dOMReader->getSiteName();

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
