<?php

declare(strict_types=1);

namespace App\Importers\Chrome;

use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\Builders\SiteBuilder;
use App\Exceptions\MalformedURLException;
use App\Importers\FilesystemInterface;
use App\Services\CreateBookmarkService;
use App\ValueObjects\Url;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

final class ImportBookmarksFromChromeBrowser
{
    public function __construct(
        private CreateBookmarkService $createBookmark,
        private FilesystemInterface $filesystem,
        private DOMParserInterface $parser
    ) {
    }

    public function import(UserID $userID, Uuid $requestID, array $requestData): void
    {
        $htmlFileContents = $this->getHtmlFileContents($userID, $requestID);

        foreach ($this->parser->parse($htmlFileContents) as $bookmark) {
            $this->saveBookmark($requestData, $userID, $bookmark);
        }

        $this->filesystem->delete($userID, $requestID);
    }

    private function getHtmlFileContents(UserID $userID, Uuid $requestID): string
    {
        if (!$this->filesystem->exists($userID, $requestID)) {
            throw new FileNotFoundException();
        }

        return $this->filesystem->get($userID, $requestID);
    }

    private function saveBookmark(array $requestData, UserID $userID, ChromeBookmark $chromeBookmark): void
    {
        try {
            $url = new Url($chromeBookmark->url);
        } catch (MalformedURLException) {
            return;
        }

        $bookmark = (new BookmarkBuilder())
            ->title($url->value)
            ->hasCustomTitle(false)
            ->url($url->value)
            ->previewImageUrl('')
            ->description(null)
            ->descriptionWasSetByUser(false)
            ->bookmarkedById($userID->toInt())
            ->site(SiteBuilder::new()->domainName($url->getHostName())->name($url->value)->build())
            ->tags($requestData['tags'] ?? [])
            ->bookmarkedOn($this->resolveTimestamp($requestData, $chromeBookmark))
            ->build();

        $this->createBookmark->create($bookmark);
    }

    private function resolveTimestamp(array $requestData, ChromeBookmark $chromeBookmark): string
    {
        $useBookmarkTimestamp = $requestData['use_timestamp'] ?? true;
        $default = (string) now();
        $addDate = $chromeBookmark->timestamp;

        if ($useBookmarkTimestamp === false || blank($addDate)) {
            return $default;
        }

        try {
            return (string) Carbon::createFromTimestamp($addDate);
        } catch (InvalidFormatException) {
            return $default;
        }
    }
}
