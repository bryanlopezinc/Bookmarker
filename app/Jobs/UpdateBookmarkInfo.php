<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Readers\HttpClientInterface;
use App\DataTransferObjects\Bookmark;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Traits\ReflectsClosures;
use App\Contracts\UpdateBookmarkRepositoryInterface as Repository;
use App\Contracts\UrlHasherInterface;
use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder as Builder;
use App\Models\WebSite;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\BookmarkDescription;
use App\ValueObjects\BookmarkTitle;

final class UpdateBookmarkInfo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(private Bookmark $bookmark)
    {
    }

    public function handle(HttpClientInterface $client, Repository $repository, UrlHasherInterface $urlHasher): void
    {
        $data = $client->fetchBookmarkPageData($this->bookmark);
        $builder = Builder::new();

        if ($data === false) {
            return;
        }

        if ($data->thumbnailUrl !== false) {
            $builder->previewImageUrl($data->thumbnailUrl);
        }

        if ($data->canonicalUrl !== false) {
            $builder->canonicalUrl($data->canonicalUrl)->canonicalUrlHash($urlHasher->hashCanonicalUrl($data->canonicalUrl));
        }

        $builder->id($this->bookmark->id->toInt());
        $builder->resolvedAt(now());
        $builder->resolvedUrl($data->reosolvedUrl);
        $this->setDescriptionAttribute($builder, $data);
        $this->seTtitleAttributes($builder, $data);
        $this->updateSiteName($data);

        $repository->update($builder->build());
    }

    private function setDescriptionAttribute(Builder &$builder, BookmarkMetaData $data): void
    {
        if ($this->bookmark->descriptionWasSetByUser) {
            return;
        }

        $description = $data->description;

        if ($description === false) {
            return;
        }

        $builder->description(BookmarkDescription::fromLongtText($description));
    }

    private function seTtitleAttributes(Builder &$builder, BookmarkMetaData $data): void
    {
        if ($this->bookmark->hasCustomTitle) {
            return;
        }

        $title = $data->title;

        if ($title === false) {
            return;
        }

        $builder->title(BookmarkTitle::fromLongtText($title));
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
