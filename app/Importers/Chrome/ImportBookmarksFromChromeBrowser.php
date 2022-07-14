<?php

declare(strict_types=1);

namespace App\Importers\Chrome;

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
        if (!$this->filesystem->exists($userID, $requestID)) {
            throw new FileNotFoundException();
        }

        foreach ($this->parser->parse($this->filesystem->get($userID, $requestID)) as $bookmark) {
            $this->saveBookmark($requestData, $userID, $bookmark);
        }

        $this->filesystem->delete($userID, $requestID);
    }

    private function saveBookmark(array $requestData, UserID $userID, ChromeBookmark $chromeBookmark): void
    {
        try {
            $url = new Url($chromeBookmark->url);
        } catch (MalformedURLException) {
            return;
        }

        $this->createBookmark->fromArray([
            'url' => $url,
            'createdOn' => $this->resolveTimestamp($requestData, $chromeBookmark),
            'userID' => $userID,
            'tags' => $requestData['tags'] ?? []
        ]);
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
