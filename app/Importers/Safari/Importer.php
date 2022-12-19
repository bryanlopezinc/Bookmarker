<?php

declare(strict_types=1);

namespace App\Importers\Safari;

use App\Exceptions\MalformedURLException;
use App\Importers\Filesystem;
use App\Importers\ImporterInterface;
use App\Services\CreateBookmarkService;
use App\ValueObjects\Url;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

final class Importer implements ImporterInterface
{
    public function __construct(
        private CreateBookmarkService $createBookmark,
        private Filesystem $filesystem,
        private DOMParserInterface $parser = new DOMParser()
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

    private function saveBookmark(array $requestData, UserID $userID, Bookmark $chromeBookmark): void
    {
        try {
            $url = new Url($chromeBookmark->url);
        } catch (MalformedURLException) {
            return;
        }

        $this->createBookmark->fromArray([
            'url' => $url,
            'createdOn' => (string) now(),
            'userID' => $userID,
            'tags' => $requestData['tags'] ?? []
        ]);
    }
}
