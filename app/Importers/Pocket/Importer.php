<?php

declare(strict_types=1);

namespace App\Importers\Pocket;

use App\Exceptions\InvalidTagException;
use App\Exceptions\MalformedURLException;
use App\Importers\ResolveImportTimestamp;
use App\Importers\FilesystemInterface;
use App\Importers\ImporterInterface;
use App\Services\CreateBookmarkService;
use App\ValueObjects\Tag;
use App\ValueObjects\Url;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

final class Importer implements ImporterInterface
{
    use ResolveImportTimestamp;

    public function __construct(
        private CreateBookmarkService $createBookmark,
        private FilesystemInterface $filesystem,
        private DOMParserInterface $parser = new DOMParser
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

    private function saveBookmark(array $requestData, UserID $userID, Bookmark $pocketBookmark): void
    {
        try {
            $url = new Url($pocketBookmark->url);
        } catch (MalformedURLException) {
            return;
        }

        $this->createBookmark->fromArray([
            'url' => $url,
            'createdOn' => $this->resolveImportTimestamp($requestData['use_timestamp'] ?? true, $pocketBookmark->timestamp),
            'userID' => $userID,
            'tags' => $this->resolveTags($requestData, $pocketBookmark)
        ]);
    }

    private function resolveTags(array $requestData, Bookmark $pocketBookmark): array
    {
        $tags = [];

        if ($requestData['ignore_tags'] ?? false) {
            return $tags;
        }

        if (count($pocketBookmark->tags) > setting('MAX_BOOKMARKS_TAGS')) {
            return $tags;
        }

        foreach ($pocketBookmark->tags as $tag) {
            if (!$this->tagIsCompatible($tag)) {
                continue;
            }

            $tags[] = $tag;
        }

        return collect($tags)->uniqueStrict()->values()->all();
    }

    private function tagIsCompatible(string $tag): bool
    {
        try {
            new Tag($tag);
            return true;
        } catch (InvalidTagException) {
            return false;
        }
    }
}
