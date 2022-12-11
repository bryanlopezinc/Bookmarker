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
use App\Utils\UrlHasher;
use App\DataTransferObjects\Builders\BookmarkBuilder as Builder;
use App\DataTransferObjects\UpdateBookmarkData as Data;
use App\Models\Source;
use App\Readers\BookmarkMetaData;
use App\Repositories\BookmarkRepository;
use App\Repositories\UserRepository;
use App\ValueObjects\BookmarkDescription;
use App\ValueObjects\BookmarkTitle;

final class UpdateBookmarkWithHttpResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(private Bookmark $bookmark)
    {
    }

    public function handle(
        HttpClientInterface $client,
        Repository $repository,
        UrlHasher $urlHasher,
        BookmarkRepository $bookmarkRepository = new BookmarkRepository,
    ): void {

        /** @var Bookmark|null */
        $bookmark = $bookmarkRepository->findManyById($this->bookmark->id->toCollection())->first();

        if ($bookmark === null) {
            return;
        }

        if ($this->userRepository()->findByID($bookmark->getOwnerID()) === false) {
            return;
        }

        $builder = Builder::new()->id($bookmark->id->value())->resolvedAt(now());

        $data = $client->fetchBookmarkPageData($bookmark);

        if ($data === false) {
            return;
        }

        $builder->resolvedUrl($data->resolvedUrl);

        if ($data->thumbnailUrl !== false) {
            $builder->thumbnailUrl($data->thumbnailUrl->toString());
        }

        if ($data->canonicalUrl !== false) {
            $builder->canonicalUrl($data->canonicalUrl)->canonicalUrlHash($urlHasher->hashUrl($data->canonicalUrl));
        }

        $this->setDescriptionAttribute($builder, $data, $bookmark);
        $this->seTittleAttributes($builder, $data, $bookmark);
        $this->updateSiteName($data, $bookmark);

        $repository->update(new Data($builder->build()));
    }

    private function userRepository(): UserRepository
    {
        return app(UserRepository::class);
    }

    private function setDescriptionAttribute(Builder &$builder, BookmarkMetaData $data, Bookmark $bookmark): void
    {
        if ($bookmark->descriptionWasSetByUser || $data->description === false) {
            return;
        }

        $builder->description(BookmarkDescription::limit($data->description)->value);
    }

    private function seTittleAttributes(Builder &$builder, BookmarkMetaData $data, Bookmark $bookmark): void
    {
        if ($bookmark->hasCustomTitle || $data->title === false) {
            return;
        }

        $builder->title(BookmarkTitle::limit($data->title)->value);
    }

    private function updateSiteName(BookmarkMetaData $data, Bookmark $bookmark): void
    {
        $siteName = $data->hostSiteName;

        if ($siteName === false || blank($siteName)) {
            return;
        }

        /** @var Source */
        $source = Source::query()->where('id', $bookmark->source->id->value())->first(['name', 'id', 'host']);

        if (!$bookmark->source->nameHasBeenUpdated) {
            $source->update([
                'name' => $siteName,
                'name_updated_at' => now()
            ]);
        }
    }
}
