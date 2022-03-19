<?php

namespace App\Jobs;

use App\DataTransferObjects\Bookmark;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Actions\UpdateBookmarkDescriptionWithMetaTag;
use App\Actions\UpdateBookmarkImageUrlWithMetaTag;
use App\Actions\UpdateBookmarkTitleWithMetaTag;
use App\Actions\UpdateSiteNameWithMetaTag;

final class UpdateBookmarkInfo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private Bookmark $bookmark)
    {
    }

    public function handle(): void
    {
        $response = Http::accept('text/html')
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36')
            ->get($this->bookmark->linkToWebPage->value);

        if (!($response->ok() || $response->redirect())) {
            return;
        }

        libxml_use_internal_errors(true);

        $documnet = new \DOMDocument;
        $documnet->loadHTML($response->body());

        (new UpdateBookmarkImageUrlWithMetaTag($documnet))($this->bookmark);
        (new UpdateBookmarkDescriptionWithMetaTag($documnet))($this->bookmark);
        (new UpdateSiteNameWithMetaTag($documnet))($this->bookmark);
        (new UpdateBookmarkTitleWithMetaTag($documnet))($this->bookmark);
    }
}
