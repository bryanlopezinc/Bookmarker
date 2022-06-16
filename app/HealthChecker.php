<?php

declare(strict_types=1);

namespace App;

use App\Collections\BookmarksCollection;
use App\Contracts\BookmarksHealthRepositoryInterface;
use App\DataTransferObjects\Bookmark;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class HealthChecker
{
    public function __construct(private BookmarksHealthRepositoryInterface $repository)
    {
    }

    public function ping(BookmarksCollection $bookmarks): void
    {
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
     * @return array<int,Response> Each key in the array is the bookmarkID and value the corresponding http response.
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
