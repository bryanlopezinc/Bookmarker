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
        $data = $client->getWebPageData($this->bookmark);

        (new Actions\UpdateBookmarkImageUrlWithMetaTag($data))($this->bookmark);
        (new Actions\UpdateBookmarkDescriptionWithMetaTag($data))($this->bookmark);
        (new Actions\UpdateSiteNameWithMetaTag($data))($this->bookmark);
        (new Actions\UpdateBookmarkTitleWithMetaTag($data))($this->bookmark);
    }
}
