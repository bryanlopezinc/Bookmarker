<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Models\Source;
use App\Models\Bookmark;
use App\Utils\UrlHasher;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Readers\BookmarkMetaData;
use App\Readers\HttpClientInterface;
use App\Repositories\UserRepository;
use App\Repositories\BookmarkRepository;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class UpdateBookmarkWithHttpResponse implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(private Bookmark $bookmark)
    {
    }

    public function handle(
        HttpClientInterface $client,
        UrlHasher $urlHasher,
        BookmarkRepository $bookmarkRepository = new BookmarkRepository(),
    ): void {

        try {
            $bookmark = $bookmarkRepository->findById($this->bookmark->id);

            $this->userRepository()->findByID($bookmark->user_id);
        } catch (UserNotFoundException | BookmarkNotFoundException) {
            return;
        }

        $bookmark->resolved_at = now();

        $data = $client->fetchBookmarkPageData($bookmark);

        if ($data === false) {
            return;
        }

        $bookmark->resolved_url = $data->resolvedUrl->toString();

        if ($data->thumbnailUrl !== false) {
            $bookmark->preview_image_url = $data->thumbnailUrl->toString();
        }

        if ($data->canonicalUrl !== false) {
            $bookmark->url_canonical = $data->canonicalUrl->toString();
            $bookmark->url_canonical_hash = $urlHasher->hashUrl($data->canonicalUrl);
        }

        $this->setDescriptionAttribute($data, $bookmark);
        $this->seTittleAttributes($data, $bookmark);
        $this->updateSiteName($data, $bookmark);

        $bookmark->save();
    }

    private function userRepository(): UserRepository
    {
        return app(UserRepository::class);
    }

    private function setDescriptionAttribute(BookmarkMetaData $data, Bookmark &$bookmark): void
    {
        if ($bookmark->description_set_by_user || $data->description === false) {
            return;
        }

        //subtract 3 from MAX_LENGTH to accommodate the 'end' (...) value
        $bookmark->description = Str::limit($data->description, $bookmark::DESCRIPTION_MAX_LENGTH - 3);
    }

    private function seTittleAttributes(BookmarkMetaData $data, Bookmark &$bookmark): void
    {
        if ($bookmark->has_custom_title || $data->title === false) {
            return;
        }

        //subtract 3 from MAX_LENGTH to accommodate the 'end' (...) value
        $bookmark->title = Str::limit($data->title, $bookmark::TITLE_MAX_LENGTH - 3);
    }

    private function updateSiteName(BookmarkMetaData $data, Bookmark &$bookmark): void
    {
        $siteName = $data->hostSiteName;

        if ($siteName === false || blank($siteName)) {
            return;
        }

        /** @var Source */
        $source = Source::query()->where('id', $bookmark->source_id)->first(['name', 'id', 'host']);

        if ($bookmark->source->name_updated_at === null) {
            $source->update([
                'name'            => $siteName,
                'name_updated_at' => now()
            ]);
        }
    }
}
