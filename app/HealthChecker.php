<?php

declare(strict_types=1);

namespace App;

use App\Collections\BookmarksCollection;
use App\DataTransferObjects\Bookmark;
use App\Repositories\BookmarksHealthRepository;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class HealthChecker
{
    private static bool $enabled = true;

    public function __construct(private BookmarksHealthRepository $repository)
    {
    }

    public static function isEnabled(): bool
    {
        return static::$enabled === true;
    }

    public static function enable(bool $enable = true): void
    {
        static::$enabled = $enable;
    }

    public function ping(BookmarksCollection $bookmarks): void
    {
        if (!static::$enabled) {
            return;
        }

        $data = [];

        $responses = $this->getResponse(
            $bookmarks->filterByIDs($this->repository->whereNotRecentlyChecked($bookmarks->ids()))
        );

        foreach ($responses as $id => $response) {
            $data[$id] = $response->status() === 404 ? false : true;
        }

        $this->repository->update($data);
    }

    /**
     * @return array<int,Response>
     */
    private function getResponse(BookmarksCollection $bookmarks): array
    {
        return Http::pool(function (Pool $pool) use ($bookmarks) {
            return collect($bookmarks)->map(function (Bookmark $bookmark) use ($pool) {
                return $pool->as((string)$bookmark->id->toInt())
                    ->accept('text/html')
                    ->get($bookmark->linkToWebPage->value);
            })->all();
        });
    }
}
