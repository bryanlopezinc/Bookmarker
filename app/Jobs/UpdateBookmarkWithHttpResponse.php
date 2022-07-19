<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Readers\HttpClientInterface;
use App\DataTransferObjects\Bookmark;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Contracts\UpdateBookmarkRepositoryInterface as Repository;
use App\Contracts\UrlHasherInterface;
use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder as Builder;
use App\Models\WebSite;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\BookmarkDescription;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\Url;

final class UpdateBookmarkWithHttpResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(private Bookmark $bookmark)
    {
    }

    public function handle(HttpClientInterface $client, Repository $repository, UrlHasherInterface $urlHasher): void
    {
        $builder = Builder::new()->id($this->bookmark->id->toInt())->resolvedAt(now());

        if (!$this->canOpenUrl($this->bookmark->url)) {
            $repository->update($builder->resolvedUrl($this->bookmark->url)->build());
            return;
        }

        $data = $client->fetchBookmarkPageData($this->bookmark);

        if ($data === false) {
            return;
        }

        $builder->resolvedUrl($data->reosolvedUrl);

        if ($data->thumbnailUrl !== false) {
            $builder->previewImageUrl($data->thumbnailUrl);
        }

        if ($data->canonicalUrl !== false) {
            $builder->canonicalUrl($data->canonicalUrl)->canonicalUrlHash($urlHasher->hashUrl($data->canonicalUrl));
        }

        $this->setDescriptionAttribute($builder, $data);
        $this->seTtitleAttributes($builder, $data);
        $this->updateSiteName($data);

        $repository->update($builder->build());
    }

    private function canOpenUrl(Url $url): bool
    {
        return $url->isHttp() || $url->isHttps();
    }

    private function setDescriptionAttribute(Builder &$builder, BookmarkMetaData $data): void
    {
        if ($this->bookmark->descriptionWasSetByUser || $data->description === false) {
            return;
        }

        $builder->description(BookmarkDescription::fromLongtText($data->description));
    }

    private function seTtitleAttributes(Builder &$builder, BookmarkMetaData $data): void
    {
        if ($this->bookmark->hasCustomTitle || $data->title === false) {
            return;
        }

        $builder->title(BookmarkTitle::fromLongtText($data->title));
    }

    private function updateSiteName(BookmarkMetaData $data): void
    {
        $sitename = $data->hostSiteName;

        if ($sitename === false || blank($sitename)) {
            return;
        }

        $site = WebSite::query()->where('id', $this->bookmark->fromWebSite->id->toInt())->first(['name', 'id', 'host']);

        if (!$this->bookmark->fromWebSite->nameHasBeenUpdated) {
            $site->update([
                'name' => $sitename,
                'name_updated_at' => now()
            ]);
        }
    }
}
