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
use App\DataTransferObjects\Builders\BookmarkBuilder as Builder;
use App\DataTransferObjects\UpdateBookmarkData as Data;
use App\Models\WebSite;
use App\Readers\BookmarkMetaData;
use App\Repositories\FetchBookmarksRepository;
use App\ValueObjects\BookmarkDescription;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\Url;

final class UpdateBookmarkWithHttpResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(private Bookmark $bookmark)
    {
    }

    public function handle(
        HttpClientInterface $client,
        Repository $repository,
        UrlHasherInterface $urlHasher,
        FetchBookmarksRepository $bookmarkRepository = new FetchBookmarksRepository
    ): void {

        /** @var Bookmark */
        $bookmark = $bookmarkRepository->findManyById($this->bookmark->id->toCollection())->first();

        if ($bookmark === null) {
            return;
        }

        $builder = Builder::new()->id($bookmark->id->toInt())->resolvedAt(now());

        if (!$this->canOpenUrl($bookmark->url)) {
            $repository->update(new Data($builder->resolvedUrl($bookmark->url)->build()));
            return;
        }

        $data = $client->fetchBookmarkPageData($bookmark);

        if ($data === false) {
            return;
        }

        $builder->resolvedUrl($data->reosolvedUrl);

        if ($data->thumbnailUrl !== false) {
            $builder->thumbnailUrl($data->thumbnailUrl->toString());
        }

        if ($data->canonicalUrl !== false) {
            $builder->canonicalUrl($data->canonicalUrl)->canonicalUrlHash($urlHasher->hashUrl($data->canonicalUrl));
        }

        $this->setDescriptionAttribute($builder, $data, $bookmark);
        $this->seTtitleAttributes($builder, $data, $bookmark);
        $this->updateSiteName($data, $bookmark);

        $repository->update(new Data($builder->build()));
    }

    private function canOpenUrl(Url $url): bool
    {
        return $url->isHttp() || $url->isHttps();
    }

    private function setDescriptionAttribute(Builder &$builder, BookmarkMetaData $data, Bookmark $bookmark): void
    {
        if ($bookmark->descriptionWasSetByUser || $data->description === false) {
            return;
        }

        $builder->description(BookmarkDescription::fromLongtText($data->description)->value);
    }

    private function seTtitleAttributes(Builder &$builder, BookmarkMetaData $data, Bookmark $bookmark): void
    {
        if ($bookmark->hasCustomTitle || $data->title === false) {
            return;
        }

        $builder->title(BookmarkTitle::fromLongtText($data->title)->value);
    }

    private function updateSiteName(BookmarkMetaData $data, Bookmark $bookmark): void
    {
        $sitename = $data->hostSiteName;

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
