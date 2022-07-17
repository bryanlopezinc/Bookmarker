<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataTransferObjects\Bookmark;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Actions;
use App\Readers\HttpClientInterface;

final class UpdateBookmarkInfo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private Bookmark $bookmark)
    {
    }

    public function handle(HttpClientInterface $client): void
    {
        $data = $client->fetchBookmarkPageData($this->bookmark);

        if ($data === false) {
            return;
        }

        (new Actions\UpdateBookmarkThumbnailWithWebPageImage($data))($this->bookmark);
        (new Actions\UpdateBookmarkDescriptionWithWebPageDescription($data))($this->bookmark);
        (new Actions\UpdateSiteNameWithWebPageSiteName($data))($this->bookmark);
        (new Actions\UpdateBookmarkTitleWithWebPageTitle($data))($this->bookmark);
        (new Actions\UpdateBookmarkCanonicalUrlWithWebPageData($data))($this->bookmark);
        (new Actions\UpdateBookmarkResolvedUrl($data))($this->bookmark);
    }
}
